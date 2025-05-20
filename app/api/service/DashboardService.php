<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Match1;
use app\model\Odd;
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
            ->where('promoted_odd.is_valid', '=', 1);

        if (!empty($params['start_date'])) {
            $query->where(
                'match.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'match.match_time',
                '<',
                Carbon::parse($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

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
     * 获取推荐的赛事
     * @param array $params
     * @return array
     */
    public function promoted(array $params): array
    {
        $query = PromotedOdd::query()
            ->join('match', 'match.id', '=', 'promoted_odd.match_id')
            ->where('promoted_odd.is_valid', '=', 1);

        if (!empty($params['start_date'])) {
            $query->where(
                'match.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'match.match_time',
                '<',
                Carbon::parse($params['end_date'])
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
            'promoted_odd.score',
            'promoted_odd.score1 AS odd_score1',
            'promoted_odd.score2 AS odd_score2',
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
            'match.has_score',
            'match.has_period1_score',
            'match.error_status',
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
            $teams = array_reduce($rows, function (array $result, array $row) {
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
                    'error_status' => $row['error_status'],
                ];
                if ($row['error_status'] === '' && isset($row['result'])) {
                    $output['result'] = [
                        'score' => $row['score'],
                        'score1' => $row['odd_score1'],
                        'score2' => $row['odd_score2'],
                        'result' => $row['result'],
                    ];
                } else {
                    $output['result'] = null;
                }

                return $output;
            }, $rows);
        }

        return $rows;
    }

    /**
     * 获取准备中的比赛
     * @return array
     */
    public function preparing(): array
    {
        //角球判断
        $settings = get_settings(['corner_enable']);
        $corner_enable = !empty($settings['corner_enable']);

        $rows = Match1::query()
            ->whereIn(
                'id',
                Odd::query()
                    ->where('status', '=', 'ready')
                    ->when(!$corner_enable, function ($query) {
                        $query->where('variety', '!=', 'corner');
                    })
                    ->select('match_id')
            )
            ->where('status', '=', '')
            ->where(
                'match_time',
                '>',
                Carbon::now()
                    ->toISOString()
            )
            ->orderBy('id')
            ->get([
                'id',
                'match_time',
                'team1_id',
                'team2_id',
                'tournament_id',
            ])
            ->toArray();

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
            $teams = array_reduce($rows, function (array $result, array $row) {
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
                return [
                    'id' => $row['id'],
                    'match_time' => $row['match_time'],
                    'tournament' => $tournaments[$row['tournament_id']],
                    'team1' => $teams[$row['team1_id']],
                    'team2' => $teams[$row['team2_id']],
                ];
            }, $rows);
        }

        return $rows;
    }
}