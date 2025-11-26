<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Match1;
use app\model\RockBallOdd;
use app\model\RockBallPromoted;
use app\model\RockBallPromotedView;
use app\model\Team;
use app\model\Tournament;
use app\model\User;
use Carbon\Carbon;
use DateTimeInterface;
use support\Db;

/**
 * 基于滚球数据提供给Luffa客户端
 */
class RockballDashboardService
{
    /**
     * 获取推荐统计数据
     * @return array
     */
    public function summary(): array
    {
        $query = RockBallPromoted::query()
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
     * 获取准备中的比赛
     * @return array
     */
    public function preparing(): array
    {
        $rows = Tournament::query()
            ->joinSub(
                Match1::query()
                    ->whereIn(
                        'id',
                        Db::table(
                            RockBallOdd::getQuery()
                                ->join('match', 'match.id', '=', 'rockball_odd.match_id')
                                ->where('rockball_odd.is_open', '=', 1)
                                ->where('rockball_odd.status', '=', '')
                                ->selectRaw("CASE WHEN rockball_odd.period = 'period1' THEN match.match_time + interval '60 minutes' ELSE match.match_time + interval '120 minutes' END AS end_time")
                                ->selectRaw("CASE WHEN rockball_odd.period = 'period1' THEN match.score1_period1 ELSE match.score1 END AS score")
                                ->addSelect('match.id')
                            , 'a'
                        )
                            ->where('a.end_time', '>', Tournament::raw('CURRENT_TIMESTAMP'))
                            ->whereNull('a.score')
                            ->distinct()
                            ->select(['a.id'])
                    )
                    ->groupBy('tournament_id', 'match_time')
                    ->select(['tournament_id', 'match_time']),
                'a',
                'a.tournament_id',
                '=',
                'tournament.id',
            )
            ->orderBy('a.match_time')
            ->get([
                'tournament.id',
                'tournament.name',
                'a.match_time',
            ])
            ->toArray();

        return array_map(fn(array $row) => [
            'id' => $row['id'],
            'match_time' => $row['match_time'],
            'tournament' => [
                'id' => $row['id'],
                'name' => $row['name'],
            ],
            'team1' => [
                'id' => $row['id'],
                'name' => $row['name'],
            ],
            'team2' => [
                'id' => $row['id'],
                'name' => $row['name'],
            ],
        ], $rows);
    }

    /**
     * 获取推荐的赛事
     * @param array $params
     * @param DateTimeInterface|string|null $expires
     * @return array
     */
    public function promoted(array $params, DateTimeInterface|string|null $expires = null): array
    {
        $query = RockBallPromoted::query()
            ->join('match', 'match.id', '=', 'rockball_promoted.match_id')
            ->where('rockball_promoted.is_valid', '=', 1);

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
            ->orderBy('rockball_promoted.match_id')
            ->orderBy('rockball_promoted.id', 'DESC');

        //查询
        $rows = $query->get([
            'rockball_promoted.id',
            'rockball_promoted.match_id',
            'rockball_promoted.result',
            'rockball_promoted.variety',
            'rockball_promoted.period',
            'rockball_promoted.type',
            'rockball_promoted.condition',
            'rockball_promoted.score',
            'rockball_promoted.score1',
            'rockball_promoted.score2',
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
     * 获取推荐的赛事
     * @param array $params
     * @param User|null $user
     * @return array
     */
    public function promotedDesktop(array $params, ?User $user = null): array
    {
        $query = RockBallPromoted::query()
            ->join('match', 'match.id', '=', 'rockball_promoted.match_id')
            ->where('rockball_promoted.is_valid', '=', 1);

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

        if (empty($user) || $user->expire_time->unix() <= time()) {
            //如果没有用户或者用户已经过期了，那么只能展示有结果的推荐
            $query->whereNotNull('rockball_promoted.result');
        }

        $query->where(
            'match.match_time',
            '<',
            $end->toISOString(),
        );

        $query
            ->orderBy('rockball_promoted.id', 'DESC')
            ->orderBy('match.match_time', 'desc')
            ->orderBy('rockball_promoted.match_id');

        //查询
        $rows = $query->get([
            'rockball_promoted.id',
            'rockball_promoted.match_id',
            'rockball_promoted.result',
            'rockball_promoted.variety',
            'rockball_promoted.period',
            'rockball_promoted.type',
            'rockball_promoted.condition',
            'rockball_promoted.score',
            'rockball_promoted.score1',
            'rockball_promoted.score2',
            'rockball_promoted.value',
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
                    'value' => $row['value'],
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
     * 基于id获取最新推荐的赛事
     * @param array $params
     * @param User|null $user
     * @return array
     */
    public function promotedById(array $params, ?User $user = null): array
    {
        $query = RockBallPromotedView::query()
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
            'value',
            'match_time',
            'team1_id',
            'team1_name',
            'team2_id',
            'team2_name',
            'tournament_id',
            'tournament_name',
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
                'value' => $row['value'],
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
