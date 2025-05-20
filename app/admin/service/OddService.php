<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Odd;
use app\model\PromotedOdd;
use app\model\Team;
use app\model\Tournament;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;

class OddService
{
    /**
     * 获取盘口抓取数据
     * @param array $params
     * @return array
     */
    public function getOddList(array $params): array
    {
        $query = Odd::query()
            ->join('match', 'match.id', '=', 'odd.match_id');
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

        //筛选数据
        if ($params['matched2'] === 1) {
            $query->whereIn('odd.status', ['promoted', 'skip']);
        } elseif ($params['matched2'] === 0) {
            $query->where('odd.status', '=', 'ignored');
        } else {
            if ($params['matched1'] === 1) {
                $query->where('odd.status', '=', 'ready');
            } elseif ($params['matched1'] === 0) {
                $query->where('odd.status', '=', '');
            }
        }

        if (isset($params['variety'])) {
            $query->where('odd.variety', '=', $params['variety']);
        }
        if (isset($params['period'])) {
            $query->where('odd.period', '=', $params['period']);
        }
        if (isset($params['promoted']) && $params['promoted'] !== -1) {
            $query->leftJoin('promoted_odd', function (JoinClause $join) {
                $join->on('promoted_odd.odd_id', '=', 'odd.id')
                    ->where('promoted_odd.is_valid', '=', 1);
            });
            if ($params['promoted']) {
                $query->whereNotNull('promoted_odd.id');
            } else {
                $query->whereNull('promoted_odd.id');
            }
        }

        //读取盘口数据
        $rows = $query
            ->orderBy('match.match_time', 'DESC')
            ->orderBy('odd.match_id')
            ->get([
                'odd.id',
                'odd.match_id',
                'odd.variety',
                'odd.period',
                'odd.type',
                'odd.condition',
                'odd.surebet_value',
                'odd.crown_value',
                'odd.crown_condition2',
                'odd.crown_value2',
                'odd.status',
                'match.match_time',
                'match.team1_id',
                'match.team2_id',
                'match.tournament_id',
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

            //查询推荐盘口
            $promotes = PromotedOdd::query()
                ->whereIn('odd_id', array_column($rows, 'id'))
                ->get([
                    'id',
                    'odd_id',
                    'result',
                    'variety',
                    'period',
                    'type',
                    'condition',
                    'score',
                    'back',
                    'final_rule',
                    'skip',
                    'is_valid',
                ])
                ->toArray();

            $promotes = array_column($promotes, null, 'odd_id');

            //写入数据
            $rows = array_map(function (array $row) use ($tournaments, $teams, $promotes) {
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
                    'surebet_value' => $row['surebet_value'],
                    'crown_value' => $row['crown_value'],
                    'crown_condition2' => $row['crown_condition2'],
                    'crown_value2' => $row['crown_value2'],
                    'status' => $row['status'],
                ];

                //推荐数据
                $promoted = $promotes[$row['id']] ?? null;
                if ($promoted) {
                    //计算结果
                    if (isset($promoted['result'])) {
                        $promoted['result'] = [
                            'score' => $promoted['score'],
                            'result' => $promoted['result'],
                        ];
                    } else {
                        $promoted['result'] = null;
                    }
                }

                $output['promoted'] = $promoted;

                return $output;
            }, $rows);
        }

        return $rows;
    }
}