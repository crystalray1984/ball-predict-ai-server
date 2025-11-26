<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\ManualPromoteOdd;
use app\model\ManualPromoteRecord;
use app\model\Match1;
use app\model\PromotedOdd;
use app\model\PromotedOddView;
use app\model\User;
use Carbon\Carbon;
use GatewayWorker\Lib\Gateway;
use support\Db;
use support\exception\BusinessError;
use Throwable;

/**
 * 手动推荐业务逻辑
 */
class ManualPromoteService
{
    /**
     * 创建单场手动推荐
     * @param array $params
     * @return void
     */
    public function createManualRecord(array $params): void
    {
        ['final_check_time' => $final_check_time] = get_settings(['final_check_time']);

        foreach ($params['odds'] as $odd) {
            /** @var Match1 $match */
            $match = Match1::query()
                ->where('id', '=', $odd['match_id'])
                ->first(['id', 'match_time']);
            if (!$match) {
                throw new BusinessError('比赛不存在');
            }

            if ($match->match_time->timestamp - Carbon::now()->timestamp <= ($final_check_time + 1) * 60) {
                throw new BusinessError('已临近比赛时间，无法创建推荐');
            }


            $odd_type = get_odd_identification($odd['type']);

            $check_type = match ($odd_type) {
                'ah' => ['ah1', 'ah2', 'draw'],
                'sum' => ['over', 'under'],
            };

            //检查是否存在同类的推荐
            $exists = ManualPromoteOdd::query()
                ->where('match_id', '=', $odd['match_id'])
                ->where('variety', '=', $odd['variety'])
                ->where('period', '=', $odd['period'])
                ->whereIn('type', $check_type)
                ->exists();
            if ($exists) {
                throw new BusinessError('已经存在相同类型的手动推荐');
            }

            //检查这场比赛是不是已经有同类的自动推荐
            $exists = PromotedOdd::query()
                ->where('match_id', '=', $odd['match_id'])
                ->where('variety', '=', $odd['variety'])
                ->where('period', '=', $odd['period'])
                ->where('odd_type', '=', $odd_type)
                ->exists();
            if ($exists) {
                throw new BusinessError('已经存在相同类型的自动推荐');
            }
        }

        $promotedIds = [];

        Db::beginTransaction();
        try {
            $record_id = ManualPromoteRecord::insertGetId([
                'type' => $params['type'],
            ]);

            $week_day = (int)Carbon::now()->startOf('week')->format('Ymd');

            foreach ($params['odds'] as $odd) {
                $manualPromoteId = ManualPromoteOdd::insertGetId([
                    'record_id' => $record_id,
                    'match_id' => $odd['match_id'],
                    'variety' => $odd['variety'],
                    'period' => $odd['period'],
                    'condition' => $odd['condition'],
                    'type' => $odd['type'],
                    'condition2' => $odd['condition2'] ?? null,
                    'type2' => $odd['type2'] ?? null,
                ]);

                //马上插入推荐
                $id = PromotedOdd::insertGetId([
                    'match_id' => $odd['match_id'],
                    'is_valid' => 1,
                    'variety' => $odd['variety'],
                    'period' => $odd['period'],
                    'condition' => $odd['condition'],
                    'type' => $odd['type'],
                    'source' => 'manual_promote_odd',
                    'source_id' => $manualPromoteId,
                    'week_day' => $week_day,
                    'odd_type' => get_odd_identification($odd['type']),
                ]);

                $lastRow = PromotedOdd::query()
                    ->where('week_day', '=', $week_day)
                    ->where('is_valid', '=', 1)
                    ->where('id', '<', $id)
                    ->orderBy('id', 'desc')
                    ->first(['week_id']);

                PromotedOdd::query()
                    ->where('id', '=', $id)
                    ->update([
                        'week_id' => $lastRow ? $lastRow->week_id + 1 : 1,
                    ]);

                ManualPromoteOdd::query()
                    ->where('id', '=', $manualPromoteId)
                    ->update(['promoted_odd_id' => $id]);

                $promotedIds[] = $id;
            }

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        if (!empty($promotedIds)) {
            //把数据抛到队列去发送luffa消息
            $content = array_map(fn(int $id) => json_enc(['id' => $id]), $promotedIds);
            rabbitmq_publish('v3:send_promoted', $content);

            //立即把推荐发送到客户端
            $promotes = PromotedOddView::query()
                ->whereIn('id', $promotedIds)
                ->orderBy('id')
                ->get([
                    'id',
                    'match_id',
                    'result',
                    'variety',
                    'period',
                    'type',
                    'condition',
                    'score',
                    'score1',
                    'score2',
                    'match_time',
                    'team1_id',
                    'team1_name',
                    'team2_id',
                    'team2_name',
                    'tournament_id',
                    'tournament_name',
                ])
                ->toArray();
            foreach ($promotes as $row) {
                $output = [
                    'id' => $row['id'],
                    'match_id' => $row['match_id'],
                    'match_time' => Carbon::parse($row['match_time']),
                    'variety' => $row['variety'],
                    'period' => $row['period'],
                    'type' => $row['type'],
                    'condition' => $row['condition'],
                    'tournament' => [
                        'id' => $row['tournament_id'],
                        'name' => $row['tournament_name'],
                    ],
                    'team1' => [
                        'id' => $row['team1_id'],
                        'name' => $row['team1_name'],
                    ],
                    'team2' => [
                        'id' => $row['team2_id'],
                        'name' => $row['team2_name'],
                    ],
                ];
                if (isset($row['result'])) {
                    $output['result'] = [
                        'score' => $row['score'],
                        'score1' => $row['score1'],
                        'score2' => $row['score2'],
                        'result' => $row['result'],
                    ];
                } else {
                    $output['result'] = null;
                }

                Gateway::sendToGroup('vip', json_enc([
                    'type' => 'promote',
                    'sub_type' => 'manual',
                    'data' => $output,
                ]));
            }
        }
    }

    /**
     * 删除记录
     * @param int $record_id
     * @param int|null $odd_id
     * @return void
     */
    public function remove(int $record_id, ?int $odd_id): void
    {
        Db::beginTransaction();
        try {
            if (!empty($odd_id)) {
                $deleted = ManualPromoteOdd::query()
                    ->where('record_id', '=', $record_id)
                    ->where('id', '=', $odd_id)
                    ->where('promoted_odd_id', '=', 0)
                    ->delete();
            } else {
                $deleted = ManualPromoteOdd::query()
                    ->where('record_id', '=', $record_id)
                    ->where('promoted_odd_id', '=', 0)
                    ->delete();
            }

            if (empty($deleted)) {
                throw new BusinessError('删除推荐失败');
            }

            //查询记录下还有没有推荐
            $count = ManualPromoteOdd::query()
                ->where('record_id', '=', $record_id)
                ->count();

            if (empty($count)) {
                ManualPromoteRecord::query()
                    ->where('id', '=', $record_id)
                    ->delete();
            }

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
        }
    }

    /**
     * 获取手动推荐列表
     * @param array $params
     * @return array
     */
    public function getList(array $params): array
    {
        $count = ManualPromoteRecord::query()->count();
        $list = ManualPromoteRecord::query()
            ->orderBy('id', 'DESC')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get()
            ->toArray();

        if (!empty($list)) {
            $odds = ManualPromoteOdd::query()
                ->join('v_match', 'v_match.id', '=', 'manual_promote_odd.match_id')
                ->whereIn('manual_promote_odd.record_id', array_column($list, 'id'))
                ->orderBy('v_match.match_time')
                ->get([
                    'manual_promote_odd.id',
                    'manual_promote_odd.record_id',
                    'manual_promote_odd.match_id',
                    'manual_promote_odd.variety',
                    'manual_promote_odd.period',
                    'manual_promote_odd.type',
                    'manual_promote_odd.condition',
                    'manual_promote_odd.type2',
                    'manual_promote_odd.condition2',
                    'manual_promote_odd.promoted_odd_id',
                    'v_match.match_time',
                    'v_match.team1_id',
                    'v_match.team1_name',
                    'v_match.team2_id',
                    'v_match.team2_name',
                    'v_match.tournament_id',
                    'v_match.tournament_name',
                    'v_match.has_score',
                    'v_match.has_period1_score',
                    'v_match.score1',
                    'v_match.score2',
                    'v_match.corner1',
                    'v_match.corner2',
                    'v_match.score1_period1',
                    'v_match.score2_period1',
                    'v_match.corner1_period1',
                    'v_match.corner2_period1',
                ])
                ->toArray();

            $promoted = [];
            if (!empty($odds)) {
                $promotedIds = array_values(
                    array_filter(array_column($odds, 'promoted_odd_id'), fn($v) => !empty($v))
                );
                if (!empty($promotedIds)) {
                    $promoted = PromotedOdd::query()
                        ->whereIn('id', $promotedIds)
                        ->get()
                        ->toArray();
                    $promoted = array_column($promoted, null, 'id');
                }
            }

            $odds = array_map(fn(array $row) => [
                ...$row,
                'team1' => [
                    'id' => $row['team1_id'],
                    'name' => $row['team1_name'],
                ],
                'team2' => [
                    'id' => $row['team2_id'],
                    'name' => $row['team2_name'],
                ],
                'tournament' => [
                    'id' => $row['tournament_id'],
                    'name' => $row['tournament_name'],
                ],
                'promoted' => $promoted[$row['promoted_odd_id']] ?? null,
            ], $odds);

            $list = array_map(fn(array $record) => [
                ...$record,
                'odds' => array_values(array_filter($odds, fn(array $odd) => $odd['record_id'] === $record['id'])),
            ], $list);
        }

        return [
            'count' => $count,
            'list' => $list,
            'summary' => $this->getSummary(),
        ];
    }

    /**
     * 手动推荐的胜率
     * @return array
     */
    public function getSummary(): array
    {
        //总推荐数据
        $promoted = PromotedOdd::query()
            ->join('match', 'match.id', '=', 'promoted_odd.match_id')
            ->join('manual_promote_odd', 'manual_promote_odd.promoted_odd_id', '=', 'promoted_odd.id')
            ->where('promoted_odd.is_valid', '=', 1)
            ->groupBy('promoted_odd.result')
            ->selectRaw('COUNT(1) AS total')
            ->addSelect(['promoted_odd.result'])
            ->get()
            ->toArray();

        $win = 0;
        $loss = 0;
        $draw = 0;
        $win_rate = 0;
        $all = 0;

        if (!empty($promoted)) {
            foreach ($promoted as $row) {
                $all += $row['total'];
                if ($row['result'] === 1) {
                    $win += $row['total'];
                } else if ($row['result'] === -1) {
                    $loss += $row['total'];
                } else if ($row['result'] === 0) {
                    $draw += $row['total'];
                }
            }
        }

        $total = $win + $loss;

        if ($total > 0) {
            $win_rate = round($win * 1000 / $total) / 10;
        }

        return [
            'total' => $all,
            'win' => $win,
            'loss' => $loss,
            'draw' => $draw,
            'win_rate' => $win_rate,
        ];
    }
}
