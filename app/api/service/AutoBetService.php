<?php declare(strict_types=1);

namespace app\api\service;

use app\model\AutoBetRecord;
use app\model\Promoted;
use app\model\PromotedView;
use Carbon\Carbon;
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

        if (!empty($params['start_date'])) {
            $query->where(
                'auto_bet_record.created_at',
                '>=',
                crown_time($params['start_date'])->toISOString(),
            );
        }

        if (!empty($params['end_date'])) {
            $query->where(
                'auto_bet_record.created_at',
                '<',
                crown_time($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
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
                'v_promoted.score',
                'v_promoted.score1',
                'v_promoted.score2',
            ])
            ->toArray();
        $list = array_map(fn(array $record) => $this->formatRecord($record), $list);

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

        $created_at = Carbon::createFromTimestampMsUTC($params['extra']['timestamp']);

        $id = AutoBetRecord::insertGetId([
            'user_id' => $userId,
            'crown_uid' => $params['crown_uid'],
            'promote_id' => $params['promote_id'],
            'bet_value' => $params['bet_value'],
            'bet_amount' => $params['bet_amount'],
            'created_at' => $created_at->toISOString(),
            'extra' => json_enc($params['extra']),
        ]);

        return $this->formatRecord(AutoBetRecord::query()
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
                'v_promoted.score',
                'v_promoted.score1',
                'v_promoted.score2',
            ])
            ->toArray());
    }

    protected function formatRecord(array $record): array
    {
        $channel = array_find(config('channel', []), fn($c) => $c['key'] === $record['channel']);
        $channel_name = !empty($channel) ? $channel['name'] : '';
        $record['channel_name'] = $channel_name;
        return $record;
    }

    /**
     * 自动投注之前的自动判断
     * @param array{
     *     match_time: string,
     *     channel: string,
     *     user_id: int
     * } $params
     * @return array
     */
    public function beforeBet(array $params): array
    {
        //基于传入的时间，计算皇冠比赛日
        $dateStart = crown_date($params['match_time']);
        $dateEnd = $dateStart->clone()->addDay();

        //首先读取频道今日的数据
        $rows = Promoted::query()
            ->join('match', 'match.id', '=', 'promoted.match_id')
            ->where('promoted.channel', '=', $params['channel'])
            ->where('promoted.is_valid', '=', 1)
            ->whereNotNull('promoted.result')
            ->where('promoted.result', '!=', 0)
            ->where('match.match_time', '>=', $dateStart->toISOString())
            ->where('match.match_time', '<', $dateEnd->toISOString())
            ->groupBy('promoted.result')
            ->select(['promoted.result'])
            ->selectRaw('count(*) as count')
            ->get()
            ->toArray();

        if (empty($rows)) {
            //今天尚未有任何有结果的数据，判定为可以下注
            return [
                'matches' => 0,
                'sub' => 0,
                'bet' => 0,
            ];
        }

        $win = 0;
        $loss = 0;
        foreach ($rows as $row) {
            switch ($row['result']) {
                case 1:
                    //胜场
                    $win = $row['count'];
                    break;
                case -1:
                    //负场
                    $loss = $row['count'];
                    break;
            }
        }

        $total = $win + $loss;

        if ($total < 3) {
            //今天的总场次小于3场，判定为不下注
            return [
                'matches' => $total,
                'sub' => $win - $loss,
                'bet' => 0,
            ];
        }

        //达到3场之后，根据如果输的场次多（或者持平）就买
        return [
            'matches' => $total,
            'sub' => $win - $loss,
            'bet' => $loss >= $win ? 1 : 0,
        ];
    }
}