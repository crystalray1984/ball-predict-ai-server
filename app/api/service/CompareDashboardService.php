<?php declare(strict_types=1);

namespace app\api\service;

use app\model\PromotedOdd;
use app\model\PromotedOddMansion;
use app\model\PromotedOddMansionView;
use app\model\User;
use Carbon\Carbon;

/**
 * 对比推荐相关业务
 */
class CompareDashboardService
{
    /**
     * 获取推荐统计数据
     * @return array
     */
    public function summary(): array
    {
        $query = PromotedOddMansion::query()
            ->where('is_valid', '=', 1);

        $total = $query->count();

        $data = $query->whereNotNull('result')
            ->groupBy('result')
            ->select([
                'result',
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
        $query = PromotedOddMansionView::query()
            ->where('is_valid', '=', 1);

        if (!empty($params['start_date'])) {
            $query->where(
                'match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }

        if (!empty($params['last_id'])) {
            $query->where('id', '>', $params['last_id']);
        }

        if (empty($user)) {
            //如果没有用户，那么只能展示有结果的推荐
            $query->whereNotNull('result');
        } else if ($user->expire_time->unix() <= time()) {
            //如果用户已经过期了，那么只能展示有结果的或者在VIP到期之前推出来的
            $query->where(function ($where) use ($user) {
                $where->whereNotNull('result')
                    ->orWhere('created_at', '<', $user->expire_time->toISOString());
            });
        }

        $query
            ->orderBy('id', 'DESC')
            ->orderBy('match_time', 'desc')
            ->orderBy('match_id');

        //查询
        $rows = $query->get([
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
            'value',
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