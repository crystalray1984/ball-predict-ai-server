<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Match1;
use app\model\Odd;
use app\model\PromotedOdd;
use app\model\Team;
use app\model\Tournament;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\exception\BusinessError;

class OddService
{
    /**
     * 获取盘口抓取数据
     * @param array $params
     * @return array
     */
    public function getOddList(array $params): array
    {
        $query = $this->createOddQuery();
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
        $params['matched2'] = $params['matched2'] ?? -1;
        switch ($params['matched2']) {
            case 1:
                $query->where('odd.status', '=', 'promoted');
                break;
            case 0:
                $query->where('odd.status', '=', 'ignored');
                break;
            case 'crown':
                $query->where('odd.status', '=', 'promoted')
                    ->where('odd.final_rule', '=', 'crown');
                break;
            case 'crown_special':
                $query->where('odd.status', '=', 'promoted')
                    ->where('odd.final_rule', '=', 'crown_special');
                break;
            default:
                if ($params['matched1'] === 1) {
                    $query->where('odd.status', '!=', '');
                } elseif ($params['matched1'] === 0) {
                    $query->where('odd.status', '=', '');
                }
                break;
        }

        if (isset($params['variety'])) {
            $query->where('odd.variety', '=', $params['variety']);
        }
        if (isset($params['period'])) {
            $query->where('odd.period', '=', $params['period']);
        }
        if (isset($params['promoted']) && $params['promoted'] !== -1) {
            $query->leftJoin('promoted_odd', function (JoinClause $join) use ($params) {
                $join->on('promoted_odd.odd_id', '=', 'odd.id');
                if ($params['promoted'] === 1) {
                    $join->where('promoted_odd.is_valid', '=', 1);
                } else if ($params['promoted'] === 2) {
                    $join->where('promoted_odd.is_valid', '=', 0);
                }
            });
            if ($params['promoted']) {
                $query->whereNotNull('promoted_odd.id');
            } else {
                $query->whereNull('promoted_odd.id');
            }
        }

        return $this->processOddList($query->get()->toArray());
    }

    /**
     * 通过比赛id获取盘口数据
     * @param int $match_id
     * @return array
     */
    public function getOddsByMatch(int $match_id): array
    {
        return $this->processOddList(
            $this->createOddQuery()
                ->where('match.id', '=', $match_id)
                ->get()
                ->toArray()
        );
    }

    /**
     * 处理查询好的盘口列表
     * @param array $rows
     * @return array
     */
    protected function processOddList(array $rows): array
    {
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
                    'skip',
                    'is_valid',
                    'final_rule',
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
                    'final_rule' => $row['final_rule'],
                    'has_score' => $row['has_score'],
                    'has_period1_score' => $row['has_period1_score'],
                    'created_at' => $row['created_at'],
                    'ready_at' => $row['ready_at'],
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

    /**
     * 创建盘口查询器
     * @return Builder
     */
    protected function createOddQuery(): Builder
    {
        return Odd::query()
            ->join('match', 'match.id', '=', 'odd.match_id')
            ->orderBy('match.match_time', 'DESC')
            ->orderBy('odd.match_id')
            ->select([
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
                'odd.final_rule',
                'odd.created_at',
                'odd.ready_at',
                'match.match_time',
                'match.team1_id',
                'match.team2_id',
                'match.tournament_id',
                'match.has_score',
                'match.has_period1_score',
            ]);
    }

    /**
     * 补充推荐数据
     * @param array $params
     * @return void
     */
    public function add(array $params): void
    {
        //先查询比赛数据
        $match = Match1::query()
            ->where('id', '=', $params['match_id'])
            ->first();
        if (!$match) {
            throw new BusinessError('未找到对应的比赛');
        }
        if (!$match->has_score) {
            throw new BusinessError('该比赛尚未有赛果');
        }

        $odd = Odd::query()
            ->where('id', '=', $params['odd_id'])
            ->where('match_id', '=', $params['match_id'])
            ->first();

        if (!$odd) {
            throw new BusinessError('未找到原始盘口');
        }

        $promoted = PromotedOdd::query()
            ->where('odd_id', '=', $params['odd_id'])
            ->first();

        if ($promoted) {
            if ($promoted->is_valid) {
                throw new BusinessError('该盘口已在推荐列表中');
            }
        } else {
            $promoted = new PromotedOdd();
            $promoted->match_id = $match->id;
            $promoted->odd_id = $params['odd_id'];
            $promoted->variety = $odd->variety;
            $promoted->period = $odd->period;
        }
        $promoted->back = $params['back'] ? 1 : 0;
        if ($params['back']) {
            [$type, $condition] = get_reverse_odd($odd->type, $odd->condition);
            $promoted->type = $type;
            $promoted->condition = $condition;
        } else {
            $promoted->type = $odd->type;
            $promoted->condition = $odd->condition;
        }
        $promoted->is_valid = 1;
        $promoted->skip = '';

        //重新计算赛果
        $result = get_odd_score($match->toArray(), $promoted->toArray());
        $promoted->result1 = $result['result'];
        $promoted->result = $promoted->result1;
        $promoted->score = $result['score'];
        $promoted->score1 = $result['score1'];
        $promoted->score2 = $result['score2'];

        $promoted->save();

        $odd->status = 'promoted';
        $odd->save();
    }

    /**
     * 导出数据列表
     * @param array $data 通过getOddList获取到的数据
     * @return string
     */
    public function exportOddList(array $data): string
    {
        //构建导出的数据
        $rows = [
            [
                '盘口id',
                '比赛id',
                '联赛',
                '比赛时间',
                '主队',
                '客队',
                '时段',
                '玩法',
                '方向',
                '盘口',
                '推送水位',
                '一次比对时间',
                '第一次皇冠水位',
                '第二次皇冠盘口',
                '第二次皇冠水位',
                '状态',
                '二次比对规则',
                '是否推荐',
                '推荐方向',
                '推荐盘口',
                '推荐规则',
                '结果',
                '对应赛果'
            ]
        ];

        //生成数据
        foreach ($data as $row) {
            //二次比对规则
            $final_rule = '';
            if ($row['status'] === 'promoted') {
                $final_rule = match ($row['final_rule']) {
                    'titan007' => '球探网趋势',
                    'crown' => '皇冠水位',
                    'crown_special' => '皇冠变盘',
                    default => '',
                };
            }

            $promoted_text = '';
            $promoted_type = '';
            $promoted_condition = '';
            $result = '';
            $result_score = '';
            $promoted_rule = '';

            if ($row['promoted']) {
                if ($row['promoted']['is_valid']) {
                    $promoted_text = '推荐';
                } else {
                    $promoted_text = match ($row['promoted']['skip']) {
                        '' => '筛选率过滤',
                        'manual_promote' => '手动推荐优先',
                        'same_type' => '同盘口过滤',
                        'setting' => '规则过滤',
                        default => '',
                    };
                }

                $promoted_type = match ($row['promoted']['type']) {
                    'ah1' => '主胜',
                    'ah2' => '客胜',
                    'over' => '大球',
                    'under' => '小球',
                    default => '',
                };
                $promoted_condition = match ($row['promoted']['type']) {
                    'ah1', 'ah2' => (bccomp($row['promoted']['condition'], '0', 2) > 0 ? '+' : '') . (float)$row['promoted']['condition'],
                    default => (string)(float)$row['promoted']['condition'],
                };
                if (!$row['promoted']['result']) {
                    $result = '待定';
                } else {
                    $result = match ($row['promoted']['result']['result']) {
                        0 => '和',
                        1 => '赢',
                        -1 => '输',
                        default => '',
                    };
                    $result_score = $row['promoted']['result']['score'];
                }

                $promoted_rule = match ($row['promoted']['final_rule']) {
                    'special' => '特殊',
                    'special_config' => '变盘',
                    'titan007' => '球探网',
                    'corner' => '角球',
                    default => '',
                };
            }


            $add_row = [
                $row['id'],
                $row['match_id'],
                $row['tournament']['name'],
                Carbon::parse($row['match_time'])->toDateTimeString(),
                $row['team1']['name'],
                $row['team2']['name'],
                match ($row['period']) {
                    'regularTime' => '全场',
                    'period1' => '半场',
                    default => '',
                },
                match ($row['variety']) {
                    'goal' => '进球',
                    'corner' => '角球',
                    default => '',
                },
                match ($row['type']) {
                    'ah1' => '主胜',
                    'ah2' => '客胜',
                    'over' => '大球',
                    'under' => '小球',
                    default => '',
                },
                match ($row['type']) {
                    'ah1', 'ah2' => (bccomp($row['condition'], '0', 2) > 0 ? '+' : '') . (float)$row['condition'],
                    default => (string)(float)$row['condition'],
                },
                $row['surebet_value'],
                !empty($row['ready_at']) ? Carbon::parse($row['ready_at'])->toDateTimeString() : '',
                $row['crown_value'],
                isset($row['crown_condition2']) ? match ($row['type']) {
                    'ah1', 'ah2' => (bccomp($row['crown_condition2'], '0', 2) > 0 ? '+' : '') . (float)$row['crown_condition2'],
                    default => (string)(float)$row['crown_condition2'],
                } : '',
                $row['crown_value2'] ?? '',
                match ($row['status']) {
                    '' => '第一次比对失败',
                    'ready' => '等待二次比对',
                    'promoted' => '二次比对成功',
                    'ignored' => '二次比对失败',
                },
                $final_rule,
                $promoted_text,
                $promoted_type,
                $promoted_condition,
                $promoted_rule,
                $result,
                $result_score,
            ];

            $rows[] = $add_row;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($rows, '');

        //输出数据
        $writer = new Xlsx($spreadsheet);
        $filePath = runtime_path() . '/' . uniqid() . '.xlsx';
        $writer->save($filePath);
        return $filePath;
    }

    /**
     * 删除已经推荐出来的记录
     * @param int $id
     * @return void
     */
    public function removePromoted(int $id): void
    {
        $row = PromotedOdd::query()
            ->where('id', '=', $id)
            ->first();
        if (!$row) {
            throw new BusinessError('未找到推荐记录');
        }

        if (!$row->is_valid) {
            throw new BusinessError('未找到推荐记录');
        }

        $row->is_valid = 0;
        $row->save();
    }
}