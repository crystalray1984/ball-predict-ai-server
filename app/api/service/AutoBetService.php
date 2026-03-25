<?php declare(strict_types=1);

namespace app\api\service;

use app\model\AutoBetRecord;
use app\model\Promoted;
use support\exception\BusinessError;

/**
 * 自动投注相关逻辑
 */
class AutoBetService
{
    /**
     * 查询投注记录
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getRecords(int $userId, array $params): array
    {
        $query = AutoBetRecord::query()
            ->join('v_promoted', 'v_promoted.id', '=', 'auto_bet_record.promote_id')
            ->where('auto_bet_record.user_id', '=', $userId);
        if (!empty($params['channel'])) {
            $query->where('v_promoted.channel', '=', $params['channel']);
        }
        if (!empty($params['crown_uid'])) {
            $query->where('auto_bet_record.crown_uid', '=', $params['crown_uid']);
        }

        $count = $query->count();
        $list = $query->orderBy('auto_bet_record.created_at', 'desc')
            ->orderBy('auto_bet_record.id', 'desc')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get([
                'auto_bet_record.*',
                'v_promoted.channel',
                'v_promoted.tournament_name',
                'v_promoted.team1_name',
                'v_promoted.team2_name',
                'v_promoted.match_time',
                'v_promoted.type',
                'v_promoted.condition',
                'v_promoted.period',
                'v_promoted.result',
            ])
            ->toArray();

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 插入投注记录
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function addRecord(int $userId, array $params): array
    {
        //检查数据
        $promoted = Promoted::query()
            ->where('id', '=', $params['promote_id'])
            ->exists();

        if (!$promoted) {
            throw new BusinessError('推荐数据不存在');
        }

        $id = AutoBetRecord::insertGetId([
            'user_id' => $userId,
            'crown_uid' => $params['crown_uid'],
            'promote_id' => $params['promote_id'],
            'bet_value' => $params['bet_value'],
            'bet_amount' => $params['bet_amount'],
            'created_at' => $params['created_at'],
            'extra' => json_enc($params['extra']),
        ]);

        return AutoBetRecord::query()
            ->join('v_promoted', 'v_promoted.id', '=', 'auto_bet_record.promote_id')
            ->where('auto_bet_record.id', '=', $id)
            ->first([
                'auto_bet_record.*',
                'v_promoted.channel',
                'v_promoted.tournament_name',
                'v_promoted.team1_name',
                'v_promoted.team2_name',
                'v_promoted.match_time',
                'v_promoted.type',
                'v_promoted.condition',
                'v_promoted.period',
                'v_promoted.result',
            ])
            ->toArray();
    }
}