<?php declare(strict_types=1);

namespace app\api\service;

use app\model\PromotedOdd;
use app\model\PromotedOddView;
use app\model\User;
use Carbon\Carbon;

class ManualDashboardService
{
    /**
     * 获取推荐统计数据
     * @return array
     */
    public function summary(): array
    {
        $query = PromotedOdd::query()
            ->join('manual_promote_odd', 'manual_promote_odd.promoted_odd_id', '=', 'promoted_odd.id')
            ->where('promoted_odd.is_valid', '=', 1)
            ->whereNull('manual_promote_odd.deleted_at');

        $total = $query->count();

        $data = $query->whereNotNull('promoted_odd.result')
            ->groupBy('promoted_odd.result')
            ->select([
                'promoted_odd.result',
            ])
            ->selectRaw('count(*) as count')
            ->get()
            ->toArray();

        if (empty($data)) {
            //没有数据
            return [
                'total' => $total,
                'win' => 0,
                'loss' => 0,
                'draw' => 0,
                'win_rate' => 0,
            ];
        }

        $data = array_column($data, 'count', 'result');

        $result = [
            'total' => $total,
            'win' => $data[1] ?? 0,
            'loss' => $data[-1] ?? 0,
            'draw' => $data[0] ?? 0,
        ];

        $total = $result['win'] + $result['loss'];
        if ($total === 0) {
            $result['win_rate'] = 0;
        } else {
            $result['win_rate'] = round($result['win'] * 1000 / $total) / 10;
        }

        return $result;
    }

    /**
     * 基于id获取最新推荐的赛事
     * @param array $params
     * @param User|null $user
     * @return array
     */
    public function promotedById(array $params, ?User $user = null): array
    {
        $query = PromotedOddView::query()
            ->join('manual_promote_odd', 'manual_promote_odd.promoted_odd_id', '=', 'v_promoted_odd.id')
            ->where('v_promoted_odd.is_valid', '=', 1)
            ->whereNull('manual_promote_odd.deleted_at');

        if (!empty($params['start_date'])) {
            $query->where(
                'v_promoted_odd.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }

        if (!empty($params['last_id'])) {
            $query->where('v_promoted_odd.id', '>', $params['last_id']);
        }

        if (empty($user)) {
            //如果没有用户，那么只能展示有结果的推荐
            $query->whereNotNull('v_promoted_odd.result');
        } else if ($user->expire_time->unix() <= time()) {
            //如果用户已经过期了，那么只能展示有结果的或者在VIP到期之前推出来的
            $query->where(function ($where) use ($user) {
                $where->whereNotNull('v_promoted_odd.result')
                    ->orWhere('v_promoted_odd.created_at', '<', $user->expire_time->toISOString());
            });
        }

        $query
            ->orderBy('v_promoted_odd.id', 'DESC')
            ->orderBy('v_promoted_odd.match_time', 'desc')
            ->orderBy('v_promoted_odd.match_id');

        //查询
        $rows = $query->get([
            'v_promoted_odd.id',
            'v_promoted_odd.match_id',
            'v_promoted_odd.result',
            'v_promoted_odd.variety',
            'v_promoted_odd.period',
            'v_promoted_odd.type',
            'v_promoted_odd.condition',
            'v_promoted_odd.score',
            'v_promoted_odd.score1',
            'v_promoted_odd.score2',
            'v_promoted_odd.match_time',
            'v_promoted_odd.team1_id',
            'v_promoted_odd.team1_name',
            'v_promoted_odd.team2_id',
            'v_promoted_odd.team2_name',
            'v_promoted_odd.tournament_id',
            'v_promoted_odd.tournament_name',
        ])
            ->toArray();

        $rows = array_map(function (array $row) {
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

            return $output;
        }, $rows);

        return $rows;
    }
}