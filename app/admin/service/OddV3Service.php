<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\CrownOdd;
use app\model\MatchView;
use app\model\Odd;
use app\model\PromotedOddChannel2;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;

class OddV3Service
{
    /**
     * 获取进行中或者有盘口数据的比赛列表
     * @param array $params
     * @return array
     */
    public function getMatchList(array $params): array
    {
        $query = MatchView::query();

        if (!empty($params['start_date'])) {
            $query->where(
                'v_match.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'v_match.match_time',
                '<',
                Carbon::parse($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        if ($params['ready_status'] === 0) {
            $query->whereNotIn('id', Odd::query()->where('status', '=', 'ready')->select(['match_id']));
        } else {
            if ($params['ready_status'] === 1) {
                $query->whereIn('id', Odd::query()->where('status', '=', 'ready')->select(['match_id']));
            }

            if (isset($params['promoted'])) {
                switch ($params['promoted']) {
                    case 1:
                        $query->whereIn('v_match.id', PromotedOddChannel2::query()->select('match_id'));
                        break;
                    case 0:
                        $query->whereNotIn('v_match.id', PromotedOddChannel2::query()->select('match_id'));
                        break;
                }
            }
        }

        $matches = $query
            ->orderBy('v_match.match_time', 'DESC')
            ->orderBy('v_match.id', 'DESC')
            ->get(['v_match.*'])
            ->toArray();

        if (empty($matches)) {
            //没有比赛
            return [];
        }

        $matchIds = array_column($matches, 'id');

        //读取触发这些比赛的盘口
        $odds = Odd::query()
            ->joinSub(
                Odd::query()
                    ->where('status', '=', 'ready')
                    ->whereIn('match_id', $matchIds)
                    ->select(['id'])
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY match_id ORDER BY ready_at ASC) AS row_no'),
                'a',
                function (JoinClause $join) {
                    $join->on('odd.id', '=', 'a.id')
                        ->where('a.row_no', '=', 1);
                }
            )
            ->select([
                'odd.id',
                'odd.match_id',
                'odd.variety',
                'odd.period',
                'odd.type',
                'odd.condition',
                'odd.surebet_value',
                'odd.crown_value',
                'odd.ready_at'
            ])
            ->get()
            ->toArray();
        //按比赛id组合
        $odds = array_column($odds, null, 'match_id');

        //读取这些比赛的推荐数据
        $_promoted = PromotedOddChannel2::query()
            ->whereIn('match_id', $matchIds)
            ->orderBy('id', 'DESC')
            ->get()
            ->toArray();

        //按比赛id组合数据
        $promoted = [];
        foreach ($_promoted as $row) {
            if (!empty($row['start_odd_data'])) {
                $row['start_odd_data'] = json_decode($row['start_odd_data'], true);
            }
            if (!empty($row['end_odd_data'])) {
                $row['end_odd_data'] = json_decode($row['end_odd_data'], true);
            }
            $promoted[$row['match_id']][] = $row;
        }

        //输出数据
        return array_map(function (array $row) use ($odds, $promoted) {
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
                'promoted' => $promoted[$row['id']] ?? [],
                'surebet' => $odds[$row['id']] ?? null
            ];
        }, $matches);
    }

    /**
     * 导出比赛数据
     * @param array $params
     * @return string
     */
    public function exportMatchList(array $params): string
    {
        $query = MatchView::query();

        if (!empty($params['start_date'])) {
            $query->where(
                'v_match.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'v_match.match_time',
                '<',
                Carbon::parse($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        if ($params['ready_status'] === 0) {
            $query->whereNotIn('id', Odd::query()->where('status', '=', 'ready')->select(['match_id']));
        } else {
            if ($params['ready_status'] === 1) {
                $query->whereIn('id', Odd::query()->where('status', '=', 'ready')->select(['match_id']));
            }

            if (isset($params['promoted'])) {
                switch ($params['promoted']) {
                    case 1:
                        $query->whereIn('v_match.id', PromotedOddChannel2::query()->select('match_id'));
                        break;
                    case 0:
                        $query->whereNotIn('v_match.id', PromotedOddChannel2::query()->select('match_id'));
                        break;
                }
            }
        }

        //创建导出文件
        $filePath = runtime_path('/' . uniqid() . '.csv');
        $fp = fopen($filePath, 'w');
        //写入UTF-8 BOM
        fwrite($fp, pack('CCC', 0xef, 0xbb, 0xbf));

        //写入表头
        fputcsv($fp, [
            '比赛时间',
            '赛事',
            '主队',
            '客队',
            '一次对比时间',
            '推荐方向',
            '推荐盘口',
            '推送水位',
            '起点时间',
            '起点水位',
            '终点时间',
            '终点水位',
            '距离开赛时间',
            '赛果',
            '输赢'
        ]);

        $query
            ->orderBy('v_match.match_time', 'DESC')
            ->orderBy('v_match.id', 'DESC')
            ->select(['v_match.*'])
            ->chunk(100, function ($matches) use (&$fp) {
                $matches = $matches->toArray();
                $matchIds = array_column($matches, 'id');

                //读取触发这些比赛的盘口
                $odds = Odd::query()
                    ->joinSub(
                        Odd::query()
                            ->where('status', '=', 'ready')
                            ->whereIn('match_id', $matchIds)
                            ->select(['id'])
                            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY match_id ORDER BY ready_at ASC) AS row_no'),
                        'a',
                        function (JoinClause $join) {
                            $join->on('odd.id', '=', 'a.id')
                                ->where('a.row_no', '=', 1);
                        }
                    )
                    ->select([
                        'odd.id',
                        'odd.match_id',
                        'odd.variety',
                        'odd.period',
                        'odd.type',
                        'odd.condition',
                        'odd.surebet_value',
                        'odd.crown_value',
                        'odd.ready_at'
                    ])
                    ->get()
                    ->toArray();
                //按比赛id组合
                $odds = array_column($odds, null, 'match_id');

                //读取这些比赛的推荐数据
                $_promoted = PromotedOddChannel2::query()
                    ->whereIn('match_id', $matchIds)
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->toArray();

                //按比赛id组合数据
                $promoted = [];
                foreach ($_promoted as $row) {
                    if (!empty($row['start_odd_data'])) {
                        $row['start_odd_data'] = json_decode($row['start_odd_data'], true);
                    }
                    if (!empty($row['end_odd_data'])) {
                        $row['end_odd_data'] = json_decode($row['end_odd_data'], true);
                    }
                    $promoted[$row['match_id']][] = $row;
                }

                //处理比赛数据
                foreach ($matches as $match) {
                    //基础数据
                    $row = [
                        Carbon::parse($match['match_time'])->format('Y-m-d H:i:s'), //比赛时间
                        $match['tournament_name'],  //赛事
                        $match['team1_name'],   //主队
                        $match['team1_name'],   //客队
                    ];

                    $surebet = $odds[$match['id']] ?? null;
                    if (!empty($surebet)) {
                        $row[] = Carbon::parse($surebet['ready_at'])->format('Y-m-d H:i:s'); //一次比对时间
                    }

                    if (empty($promoted[$match['id']])) {
                        //没有推荐数据，只写入基础数据
                        $row = array_merge($row, [
                            '', //推送方向
                            '', //推送盘口
                            '', //推送水位
                            '', //起点时间
                            '', //起点水位
                            '', //终点时间
                            '', //终点水位
                            '', //距离开赛时间
                            '', //赛果
                            '' //输赢
                        ]);

                        fputcsv($fp, $row);
                    } else {
                        //有推荐数据
                        foreach ($promoted[$match['id']] as $promoteData) {
                            $promoteRow = [...$row];

                            //推荐方向
                            $promoteRow[] = match ($promoteData['type']) {
                                'ah1' => '主队',
                                'ah2' => '客队',
                                'under' => '小球',
                                'over' => '大球',
                                default => '',
                            };

                            //推荐盘口
                            $condition = floatval($promoteData['condition']);
                            $promoteRow[] = match ($promoteData['type']) {
                                'ah1', 'ah2' => $condition <= 0 ? strval($condition) : "+$condition",
                                default => $condition,
                            };

                            //推荐水位
                            $promoteRow[] = floatval($promoteData['value']);

                            //起点数据
                            if (!empty($promoteData['start_odd_data'])) {
                                //起点时间
                                $promoteRow[] = Carbon::createFromTimestampMs($promoteData['start_odd_data']['time'])->format('Y-m-d H:i:s');
                                //起点水位
                                $promoteRow[] = floatval($promoteData['start_odd_data']['value']);
                            } else {
                                $promoteRow[] = '';
                                $promoteRow[] = '';
                            }

                            //终点数据
                            if (!empty($promoteData['end_odd_data'])) {
                                //终点时间
                                $promoteRow[] = Carbon::createFromTimestampMs($promoteData['end_odd_data']['time'])->format('Y-m-d H:i:s');
                                //终点水位
                                $promoteRow[] = floatval($promoteData['end_odd_data']['value']);
                            } else {
                                $promoteRow[] = '';
                                $promoteRow[] = '';
                            }

                            //距离开赛时间
                            $promoteRow[] = duration(
                                (int)Carbon::parse($match['match_time'])->diffInSeconds(Carbon::parse($promoteData['created_at'])),
                            );

                            //赛果
                            if (isset($promoteData['result'])) {
                                //赛果
                                $promoteRow[] = $promoteData['score'];
                                //输赢
                                $promoteRow[] = match ($promoteData['result']) {
                                    1 => '赢',
                                    -1 => '输',
                                    0 => '和'
                                };
                            } else {
                                //赛果
                                $promoteRow[] = '';
                                //输赢
                                $promoteRow[] = '';
                            }

                            fputcsv($fp, $promoteRow);
                        }
                    }
                }
            });

        fclose($fp);
        return $filePath;
    }

    /**
     * 获取指定比赛的盘口列表
     * @param int $matchId
     * @param string $type
     * @return array
     */
    public function getOddRecords(int $matchId, string $type): array
    {
        return CrownOdd::query()
            ->where('match_id', '=', $matchId)
            ->where('type', '=', $type)
            ->where('variety', '=', 'goal')
            ->where('period', '=', 'regularTime')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }
}