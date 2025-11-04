<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\ManualPromoteOdd;
use app\model\ManualPromoteRecord;
use app\model\Match1;
use app\model\PromotedOdd;
use app\model\PromotedOddChannel2;
use Carbon\Carbon;
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

            $check_type = match ($odd['type']) {
                'ah1', 'ah2' => ['ah1', 'ah2', 'draw'],
                'over', 'under' => ['over', 'under'],
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
            $exists = PromotedOddChannel2::query()
                ->where('match_id', '=', $odd['match_id'])
                ->where('variety', '=', $odd['variety'])
                ->where('period', '=', $odd['period'])
                ->whereIn('type', $check_type)
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
                $id = PromotedOddChannel2::insertGetId([
                    'match_id' => $odd['match_id'],
                    'is_valid' => 1,
                    'variety' => $odd['variety'],
                    'period' => $odd['period'],
                    'condition' => $odd['condition'],
                    'type' => $odd['type'],
                    'manual_promote_odd_id' => $manualPromoteId,
                    'week_day' => $week_day,
                ]);


                PromotedOddChannel2::query()
                    ->where('id', '=', $id)
                    ->update([
                        'week_id' => PromotedOddChannel2::query()
                            ->where('week_day', '=', $week_day)
                            ->where('is_valid', '=', 1)
                            ->where('id', '<=', $id)
                            ->count()
                    ]);

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
            rabbitmq_publish('send_promoted_channel2', $content);
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
                ->join('match', 'match.id', '=', 'manual_promote_odd.match_id')
                ->whereIn('manual_promote_odd.record_id', array_column($list, 'id'))
                ->orderBy('match.match_time')
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
                    'match.match_time',
                    'match.team1_id',
                    'match.team2_id',
                    'match.tournament_id',
                    'match.has_score',
                    'match.has_period1_score',
                    'match.score1',
                    'match.score2',
                    'match.corner1',
                    'match.corner2',
                    'match.score1_period1',
                    'match.score2_period1',
                    'match.corner1_period1',
                    'match.corner2_period1',
                ])
                ->toArray();

            $odds = G(OddService::class)->processOddList($odds, false, true);

            $list = array_map(fn(array $record) => [
                ...$record,
                'odds' => array_values(array_filter($odds, fn(array $odd) => $odd['record_id'] === $record['id'])),
            ], $list);
        }

        return [
            'count' => $count,
            'list' => $list,
        ];
    }
}
