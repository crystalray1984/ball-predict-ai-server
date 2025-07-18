<?php declare(strict_types=1);

namespace app\api\service;

use app\model\ManualPromoteOdd;
use app\model\Match1;
use app\model\MatchView;
use app\model\Odd;
use app\model\PromotedOdd;
use app\model\Team;
use app\model\Tournament;
use Carbon\Carbon;
use DateTimeInterface;

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
     * @param DateTimeInterface|string|null $expires
     * @return array
     */
    public function promoted(array $params, DateTimeInterface|string|null $expires = null): array
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
            $end = Carbon::parse($params['end_date'])->addDays();
        } else {
            $end = Carbon::now()->addDays();
        }

        if (!empty($expires)) {
            $expire_time = Carbon::parse($expires);
            $end = Carbon::createFromTimestampMs(
                min(
                    $end->getTimestampMs(),
                    $expire_time->getTimestampMs()
                )
            );
        }

        $query->where(
            'match.match_time',
            '<',
            $end->toISOString(),
        );

        //排序
        $query
            ->orderBy('match.match_time', $params['sort_order'] ?? 'desc')
            ->orderBy('promoted_odd.match_id')
            ->orderBy('promoted_odd.odd_id');

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
            'promoted_odd.score1',
            'promoted_odd.score2',
            'match.match_time',
            'match.team1_id',
            'match.team2_id',
            'match.tournament_id',
            'match.error_status',
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
                $output = [
                    'id' => $row['id'],
                    'match_id' => $row['match_id'],
                    'match_time' => Carbon::parse($row['match_time']),
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
                        'score1' => $row['score1'],
                        'score2' => $row['score2'],
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
        ['allow_corner_preparing' => $allowCorner] = get_settings(['allow_corner_preparing']);

        $rows = MatchView::query()
            ->where(function ($query) use ($allowCorner) {
                $query->whereIn('id', Odd::query()
                    ->where('status', '=', 'ready')
                    ->when(empty($allowCorner), fn($query) => $query->where('variety', '!=', 'corner'))
                    ->select('match_id'))
                    ->orWhereIn('id', ManualPromoteOdd::query()
                        ->where('promoted_odd_id', '=', 0)
                        ->select('match_id')
                    );
            })
            ->where('status', '=', '')
            ->where(
                'match_time',
                '>',
                Carbon::now()
                    ->toISOString()
            )->orderBy('match_time')
            ->get()
            ->toArray();

        //组装数据
        return array_map(function (array $row) {
            return [
                'id' => $row['id'],
                'match_time' => $row['match_time'],
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
        }, $rows);
    }
}