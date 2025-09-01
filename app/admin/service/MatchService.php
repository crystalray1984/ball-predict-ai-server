<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\ManualPromoteOdd;
use app\model\ManualPromoteRecord;
use app\model\Match1;
use app\model\MatchView;
use app\model\PromotedOdd;
use app\model\PromotedOddChannel2;
use app\model\Tournament;
use Carbon\Carbon;
use support\Db;
use support\exception\BusinessError;
use Throwable;

/**
 * 与比赛相关的业务逻辑
 */
class MatchService
{
    /**
     * 获取需要获取赛果的比赛列表
     * @return array
     */
    public function getRequireScoreMatches(): array
    {
        $now = time();

        $list = Match1::query()
            ->join('team AS team1', 'team1.id', '=', 'match.team1_id')
            ->join('team AS team2', 'team2.id', '=', 'match.team2_id')
            ->join('odd', 'odd.match_id', '=', 'match.id')
            ->where('match.match_time', '<=', Carbon::createFromTimestamp($now)->subMinutes(45)->toISOString())
            ->where('match.match_time', '>=', Carbon::createFromTimestamp($now)->subDays(2)->toISOString())
            ->where('match.has_score', '=', false)
            ->where('match.error_status', '=', '')
            ->distinct()
            ->orderBy('match.match_time')
            ->get([
                'match.id',
                'match.match_time',
                'team1.name AS team1',
                'team2.name AS team2',
                'match.has_period1_score'
            ])
            ->toArray();

        $output = [];
        foreach ($list as $match) {
            $match_time = Carbon::parse($match['match_time'])->timestamp;
            if ($now - $match_time >= 105 * 60) {
                //可以获得全场数据了
                $output[] = [
                    'id' => $match['id'],
                    'match_time' => $match['match_time'],
                    'team1' => $match['team1'],
                    'team2' => $match['team2'],
                    'period1' => false,
                ];
            } elseif (!$match['has_period1_score']) {
                //还没有半场数据
                $output[] = [
                    'id' => $match['id'],
                    'match_time' => $match['match_time'],
                    'team1' => $match['team1'],
                    'team2' => $match['team2'],
                    'period1' => true,
                ];
            }
        }

        return $output;
    }

    /**
     * 设置赛果
     * @param array $data 赛果数据
     * @return void
     */
    public function setMatchScore(array $data): void
    {
        //首先读取所有需要更新的推荐盘口
        $query = PromotedOdd::query()
            ->where('match_id', '=', $data['match_id']);
        if ($data['period1']) {
            $query->where('period', '=', 'period1');
        }
        $odds = $query
            ->get([
                'id',
                'variety',
                'period',
                'type',
                'condition',
                'type2',
                'condition2',
            ])
            ->toArray();

        $query2 = PromotedOddChannel2::query()
            ->where('match_id', '=', $data['match_id']);
        if ($data['period1']) {
            $query2->where('period', '=', 'period1');
        }
        $odds2 = $query2
            ->get([
                'id',
                'variety',
                'period',
                'type',
                'condition',
                'type2',
                'condition2',
            ])
            ->toArray();

        //然后进行赛果计算
        $updates = [];
        $updates2 = [];

        foreach ($odds as $odd) {
            //角球无数据的判断
            if ($odd['variety'] === 'corner') {
                if ($odd['period'] === 'period1') {
                    if (!is_int($data['corner1_period1']) || !is_int($data['corner2_period1'])) {
                        continue;
                    }
                } else {
                    if (!is_int($data['corner1']) || !is_int($data['corner2'])) {
                        continue;
                    }
                }
            }

            //计算第一赛果
            $update = [];
            $result1 = get_odd_score($data, $odd);
            $update['score'] = $result1['score'];
            $update['score1'] = $result1['score1'];
            $update['score2'] = $result1['score2'];
            $update['result1'] = $result1['result'];

            if (isset($odd['type2'])) {
                //有设置了第二盘口的，就计算第二赛果
                $result2 = get_odd_score($data, [
                    'variety' => $odd['variety'],
                    'period' => $odd['variety'],
                    'type' => $odd['type2'],
                    'condition' => $odd['condition2'],
                ]);
                $update['result2'] = $result2['result'];

                //两个结果合并起来作为最终推荐的结果
                if ($result1['result'] > 0 || $result2['result'] > 0) {
                    //两个盘口只要有一个赢了就是赢
                    $update['result'] = 1;
                } elseif ($result1['result'] < 0 || $result2['result'] < 0) {
                    //没有赢的盘，那么两个盘口中有一个输就是输
                    $update['result'] = -1;
                } else {
                    $update['result'] = 0;
                }
            } else {
                //没有设置第二盘口就直接把第一盘口的结果当成最终结果
                $update['result'] = $update['result1'];
            }

            $updates[$odd['id']] = $update;
        }

        foreach ($odds2 as $odd2) {
            //角球无数据的判断
            if ($odd2['variety'] === 'corner') {
                if ($odd['period'] === 'period1') {
                    if (!is_int($data['corner1_period1']) || !is_int($data['corner2_period1'])) {
                        continue;
                    }
                } else {
                    if (!is_int($data['corner1']) || !is_int($data['corner2'])) {
                        continue;
                    }
                }
            }

            //计算第一赛果
            $update = [];
            $result1 = get_odd_score($data, $odd2);
            $update['score'] = $result1['score'];
            $update['score1'] = $result1['score1'];
            $update['score2'] = $result1['score2'];
            $update['result1'] = $result1['result'];

            if (isset($odd['type2'])) {
                //有设置了第二盘口的，就计算第二赛果
                $result2 = get_odd_score($data, [
                    'variety' => $odd['variety'],
                    'period' => $odd['variety'],
                    'type' => $odd['type2'],
                    'condition' => $odd['condition2'],
                ]);
                $update['result2'] = $result2['result'];

                //两个结果合并起来作为最终推荐的结果
                if ($result1['result'] > 0 || $result2['result'] > 0) {
                    //两个盘口只要有一个赢了就是赢
                    $update['result'] = 1;
                } elseif ($result1['result'] < 0 || $result2['result'] < 0) {
                    //没有赢的盘，那么两个盘口中有一个输就是输
                    $update['result'] = -1;
                } else {
                    $update['result'] = 0;
                }
            } else {
                //没有设置第二盘口就直接把第一盘口的结果当成最终结果
                $update['result'] = $update['result1'];
            }

            $updates2[$odd2['id']] = $update;
        }

        Db::beginTransaction();
        try {
            //首先设置赛果
            if ($data['period1']) {
                Match1::query()
                    ->where('id', '=', $data['match_id'])
                    ->update([
                        'score1_period1' => $data['score1_period1'],
                        'score2_period1' => $data['score2_period1'],
                        'corner1_period1' => $data['corner1_period1'],
                        'corner2_period1' => $data['corner2_period1'],
                        'has_period1_score' => true,
                    ]);
            } else {
                Match1::query()
                    ->where('id', '=', $data['match_id'])
                    ->update([
                        'score1' => $data['score1'],
                        'score2' => $data['score2'],
                        'corner1' => $data['corner1'],
                        'corner2' => $data['corner2'],
                        'score1_period1' => $data['score1_period1'],
                        'score2_period1' => $data['score2_period1'],
                        'corner1_period1' => $data['corner1_period1'],
                        'corner2_period1' => $data['corner2_period1'],
                        'has_score' => true,
                        'has_period1_score' => true,
                    ]);
            }

            //然后设置推荐盘口的结果
            foreach ($updates as $oddId => $update) {
                PromotedOdd::query()
                    ->where('id', '=', $oddId)
                    ->update($update);
            }

            foreach ($updates2 as $oddId => $update) {
                PromotedOddChannel2::query()
                    ->where('id', '=', $oddId)
                    ->update($update);
            }

            //找到这些推荐的盘口中，那些是手动推荐的盘口而且是赢了的，反过来去找他们的手动推荐的其他场次，标记为不再推荐
            $winOdds = array_filter($updates, fn(array $update) => $update['result'] === 1);
            if (!empty($winOdds)) {
                $winOddIds = array_keys($winOdds);
                ManualPromoteOdd::query()
                    ->whereIn(
                        'record_id',
                        ManualPromoteOdd::query()
                            ->whereIn('promoted_odd_id', $winOddIds)
                            ->select(['record_id'])
                    )
                    ->where('promoted_odd_id', '=', 0)
                    ->update(['promoted_odd_id' => -1]);
            }

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 批量设置赛果
     * @param array $list 赛果列表
     * @return void
     */
    public function multiSetMatchScore(array $list): void
    {
        if (empty($list)) return;
        Db::beginTransaction();
        try {
            foreach ($list as $data) {
                $this->setMatchScore($data);
            }
            Db::commit();
        } catch (Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    /**
     * 获取赛事列表
     * @param array $params
     * @return array
     */
    public function getTournamentList(array $params): array
    {
        $query = Tournament::query();
        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . $params['name'] . '%');
        }
        return $query
            ->orderBy('name')
            ->get(['id', 'name', 'is_open'])
            ->toArray();
    }

    /**
     * 获取比赛列表
     * @param array $params
     * @return array
     */
    public function getMatchList(array $params): array
    {
        $query = MatchView::query();

        if (!empty($params['start_date'])) {
            $query->where(
                'v_match.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'v_match.match_time',
                '<',
                Carbon::parse($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }
        if (!empty($params['tournament_id'])) {
            $query->where('v_match.tournament_id', '=', $params['tournament_id']);
        }
        if (!empty($params['team_id'])) {
            $query->where(function ($where) use ($params) {
                $where->where('v_match.team1_id', '=', $params['team_id'])
                    ->orWhere('v_match.team2_id', '=', $params['team_id']);
            });
        } elseif (!empty($params['team'])) {
            $query->where(function ($where) use ($params) {
                $where->where('v_match.team1_name', 'like', '%' . $params['team'] . '%')
                    ->orWhere('v_match.team2_name', 'like', '%' . $params['team'] . '%');
            });
        }

        if (!empty($params['status'])) {
            $query->whereIn('v_match.status', $params['status']);
        }

        $count = $query->count();
        $list = $query
            ->orderBy('v_match.match_time', 'DESC')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get()
            ->toArray();

        $list = array_map(function (array $row) {
            $row['team1'] = [
                'id' => $row['team1_id'],
                'name' => $row['team1_name'],
            ];
            $row['team2'] = [
                'id' => $row['team2_id'],
                'name' => $row['team2_name'],
            ];
            $row['tournament'] = [
                'id' => $row['tournament_id'],
                'name' => $row['tournament_name'],
            ];
            unset(
                $row['status'],
                $row['team1_id'],
                $row['team1_name'],
                $row['team2_id'],
                $row['team2_name'],
                $row['tournament_id'],
                $row['tournament_name'],
                $row['created_at'],
                $row['updated_at'],
            );
            return $row;
        }, $list);

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 获取单个比赛的信息
     * @param int $match_id
     * @return array
     */
    public function getMatch(int $match_id): array
    {
        $match = MatchView::query()
            ->where('id', '=', $match_id)
            ->first();
        if (!$match) {
            throw new BusinessError('未找到指定的比赛');
        }
        $match = $match->toArray();

        $match['team1'] = [
            'id' => $match['team1_id'],
            'name' => $match['team1_name'],
        ];
        $match['team2'] = [
            'id' => $match['team2_id'],
            'name' => $match['team2_name'],
        ];
        $match['tournament'] = [
            'id' => $match['tournament_id'],
            'name' => $match['tournament_name'],
        ];
        unset(
            $match['status'],
            $match['team1_id'],
            $match['team1_name'],
            $match['team2_id'],
            $match['team2_name'],
            $match['tournament_id'],
            $match['tournament_name'],
            $match['created_at'],
            $match['updated_at'],
        );
        return $match;
    }

    /**
     * 设置比赛的异常状态
     * @param int $match_id
     * @param int $error_status
     * @return void
     */
    public function setMatchErrorStatus(int $match_id, int $error_status): void
    {
        Match1::query()
            ->where('id', '=', $match_id)
            ->update([
                'error_status' => $error_status,
            ]);
    }
}