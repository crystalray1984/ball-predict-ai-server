<?php declare(strict_types=1);

namespace app\api\service;

use app\model\PromotedOdd;
use app\model\Team;
use app\model\Tournament;
use Carbon\Carbon;

/**
 * 首页看板业务
 */
class DashboardService
{
    /**
     * 获取推荐统计数据
     * @param array $params
     * @return array
     */
    public function summary(array $params): array
    {
        $query = PromotedOdd::query()
            ->join('match', 'match.id', '=', 'promoted_odd.match_id')
            ->whereNotNull('promoted_odd.result');

        if (!empty($params['start_date'])) {
            $query->where(
                'match.match_time',
                '>=',
                Carbon::createFromTimeString($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'match.match_time',
                '<',
                Carbon::createFromTimeString($params['start_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        $data = $query->groupBy('promoted_odd.result')
            ->select([
                'promoted_odd.result',
            ])
            ->selectRaw('count(*) as count')
            ->get()
            ->toArray();

        if (empty($data)) {
            //没有数据
            return [
                'total' => 0,
                'win' => 0,
                'loss' => 0,
                'draw' => 0,
                'win_rate' => 0,
            ];
        }

        $data = array_column($data, 'count', 'result');
        $total = array_sum($data);
        $result = [
            'total' => $total,
            'win' => $data[1] ?? 0,
            'loss' => $data[-1] ?? 0,
            'draw' => $data[0] ?? 0,
        ];

        $result['win_rate'] = round($result['win'] * 100 / $total) / 100;

        return $result;
    }

    /**
     * 获取推荐的赛事
     * @param array $params
     * @return array
     */
    public function promoted(array $params): array
    {
        $query = PromotedOdd::query()
            ->join('match', 'match.id', '=', 'promoted_odd.match_id');

        if (!empty($params['start_date'])) {
            $query->where(
                'match.match_time',
                '>=',
                Carbon::createFromTimeString($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'match.match_time',
                '<',
                Carbon::createFromTimeString($params['start_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        //排序
        switch ($params['order'] ?? null) {
            case 'match_time':
                $query->orderBy('match.match_time', $params['sort_order'] ?? 'desc');
                break;
            default:
                $query->orderBy('promoted_odd.id', $params['sort_order'] ?? 'desc');
                break;
        }

        //查询
        $rows = $query->get([
            'promoted_odd.id',
            'promoted_odd.match_id',
            'promoted_odd.result',
            'promoted_odd.variety',
            'promoted_odd.period',
            'promoted_odd.type',
            'promoted_odd.condition',
            'match.match_time',
            'match.team1_id',
            'match.team2_id',
            'match.tournament_id',
            'match.score1',
            'match.score2',
            'match.score1_period1',
            'match.score2_period1',
            'match.corner1',
            'match.corner2',
            'match.corner1_period1',
            'match.corner2_period1',
            'match.has_score'
        ])->toArray();

        if (!empty($rows)) {
            //查询赛事
            $tournaments = Tournament::query()
                ->whereIn('id', array_unique(
                    array_column($rows, 'tournament_id')
                ))
                ->get(['id', 'name'])
                ->toArray();
            $tournaments = array_column($tournaments, null, 'id');

            //查询队伍
            $teams = array_reduce($tournaments, function (array $result, array $row) {
                $result[] = $row['team1_id'];
                $result[] = $row['team2_id'];
                return $result;
            }, []);
            $teams = Team::query()
                ->whereIn('id', array_unique($teams))
                ->get(['id', 'name'])
                ->toArray();
            $teams = array_column($teams, null, 'id');

            //写入数据
            $rows = array_map(function (array $row) use ($tournaments, $teams) {
                $output = [
                    'id' => $row['id'],
                    'match_id' => $row['match_id'],
                    'match_time' => $row['match_time'],
                    'variety' => $row['variety'],
                    'period' => $row['period'],
                    'type' => $row['type'],
                    'condition' => $row['condition'],
                    'tournament' => $tournaments[$row['tournament_id']],
                    'team1' => $teams[$row['team1_id']],
                    'team2' => $teams[$row['team2_id']],
                ];

                //计算结果
                $result = null;
                if ($row['has_score']) {
                    if ($row['variety'] === 'goal') {
                        //进球
                        if ($row['period'] === 'period1') {
                            //上半场
                        } else {
                            //全场
                        }
                    } elseif ($row['variety'] === 'corner') {
                        //角球
                        if ($row['period'] === 'period1') {
                            //上半场
                        } else {
                            //全场
                        }
                    }
                }

                $output['result'] = $result;

                return $output;
            }, $rows);
        }
    }
}