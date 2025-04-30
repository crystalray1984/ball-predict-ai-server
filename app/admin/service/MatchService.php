<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Match1;
use app\model\MatchView;
use app\model\PromotedOdd;
use app\model\Tournament;
use Carbon\Carbon;
use support\Db;
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
        if ($data['period1']) {
            //设置半场赛果

            //查询比赛所有的已推荐盘口
            $odds = PromotedOdd::query()
                ->where('match_id', '=', $data['match_id'])
                ->where('period', '=', 'period1')
                ->get([
                    'id',
                    'variety',
                    'period',
                    'type',
                    'condition'
                ])
                ->toArray();

            Db::beginTransaction();
            try {
                //设置比赛的结果
                Match1::query()
                    ->where('id', '=', $data['match_id'])
                    ->update([
                        'score1_period1' => $data['score1_period1'],
                        'score2_period1' => $data['score2_period1'],
                        'corner1_period1' => $data['corner1_period1'],
                        'corner2_period1' => $data['corner2_period1'],
                        'has_period1_score' => true,
                    ]);

                //设置推荐盘口的结果
                if (!empty($odds)) {
                    foreach ($odds as $odd) {
                        //角球无数据的判断
                        if ($odd['variety'] === 'corner') {
                            if (!is_int($data['corner1_period1']) || !is_int($data['corner2_period1'])) {
                                continue;
                            }
                        }

                        $result = get_odd_score($data, $odd);
                        PromotedOdd::query()
                            ->where('id', '=', $odd['id'])
                            ->update([
                                'score' => $result['score'],
                                'result' => $result['result'],
                            ]);
                    }
                }

                Db::commit();
            } catch (Throwable $exception) {
                Db::rollBack();
                throw $exception;
            }
        } else {
            //设置全场赛果

            //查询比赛所有的已推荐盘口
            $odds = PromotedOdd::query()
                ->where('match_id', '=', $data['match_id'])
                ->get([
                    'id',
                    'variety',
                    'period',
                    'type',
                    'condition'
                ])
                ->toArray();

            Db::beginTransaction();
            try {
                //设置比赛的结果
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

                //设置推荐盘口的结果
                if (!empty($odds)) {
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

                        $result = get_odd_score($data, $odd);
                        PromotedOdd::query()
                            ->where('id', '=', $odd['id'])
                            ->update([
                                'score' => $result['score'],
                                'result' => $result['result'],
                            ]);
                    }
                }

                Db::commit();
            } catch (Throwable $exception) {
                Db::rollBack();
                throw $exception;
            }
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
            ->orderBy('name', 'asc')
            ->get(['id', 'name'])
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
            $query->where('tournament_id', '=', $params['tournament_id']);
        }
        if (!empty($params['team_id'])) {
            $query->where(function ($where) use ($params) {
                $where->where('team1_id', '=', $params['team_id'])
                    ->orWhere('team2_id', '=', $params['team_id']);
            });
        } elseif (!empty($params['team'])) {
            $query->where(function ($where) use ($params) {
                $where->where('team1_name', 'like', '%' . $params['team'] . '%')
                    ->orWhere('team2_name', 'like', '%' . $params['team'] . '%');
            });
        }
        $count = $query->count();
        $list = $query
            ->orderBy('v_match.match_time', 'DESC')
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