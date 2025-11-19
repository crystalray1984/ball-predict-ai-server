<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\MatchView;
use app\model\Odd;
use app\model\OddMansion;
use app\model\PromotedOdd;
use app\model\PromotedOddMansion;
use app\model\Team;
use app\model\Tournament;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MansionService
{
    /**
     * 获取盘口抓取数据
     * @param array $params
     * @param \Closure|null $callback
     * @param int $batchSize
     * @return array
     */
    public function getOddList(array $params, ?\Closure $callback = null, int $batchSize = 1000): array
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

        if (isset($params['variety'])) {
            $query->where('odd_mansion.variety', '=', $params['variety']);
        }
        if (isset($params['period'])) {
            $query->where('odd_mansion.period', '=', $params['period']);
        }

        if (isset($params['ready_status']) && $params['ready_status'] !== -1) {
            if ($params['ready_status'] === 0) {
                $query->where('odd_mansion.status', '=', '');
            } else if ($params['ready_status'] === 1) {
                $query->where('odd_mansion.status', '=', 'ready');
            }
        }

        if (isset($params['promoted']) && $params['promoted'] !== -1) {
            $query->leftJoin('promoted_odd_mansion', function (JoinClause $join) use ($params) {
                $join->on('promoted_odd_mansion.odd_mansion_id', '=', 'odd_mansion.id');
                if ($params['promoted'] === 1) {
                    $join->where('promoted_odd_mansion.is_valid', '=', 1);
                } else if ($params['promoted'] === 2) {
                    $join->where('promoted_odd_mansion.is_valid', '=', 0);
                }
            });
            if ($params['promoted']) {
                $query->whereNotNull('promoted_odd_mansion.id');
            } else {
                $query->whereNull('promoted_odd_mansion.id');
            }
        }

        if (!empty($callback)) {
            $query->chunk($batchSize, function ($chunk) use (&$callback) {
                $data = $this->processOddList($chunk->toArray());
                $callback($data);
            });
            return [];
        }

        return $this->processOddList($query->get()->toArray());
    }

    /**
     * 处理查询好的盘口列表
     * @param array $rows
     * @return array
     */
    public function processOddList(array $rows): array
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
            $promotes = PromotedOddMansion::query()
                ->whereIn('odd_mansion_id', array_column($rows, 'id'))
                ->get([
                    'id',
                    'odd_mansion_id',
                    'result',
                    'variety',
                    'period',
                    'type',
                    'condition',
                    'score',
                    'back',
                    'skip',
                    'is_valid',
                ])
                ->toArray();

            $promotes = array_column($promotes, null, 'odd_mansion_id');

            //写入数据
            $rows = array_map(function (array $row) use ($tournaments, $teams, $promotes) {
                $output = [
                    ...$row,
                    'tournament' => $tournaments[$row['tournament_id']],
                    'team1' => $teams[$row['team1_id']],
                    'team2' => $teams[$row['team2_id']],
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
        return OddMansion::query()
            ->join('match', 'match.id', '=', 'odd_mansion.match_id')
            ->orderBy('match.match_time', 'DESC')
            ->orderBy('odd_mansion.match_id')
            ->orderBy('odd_mansion.id')
            ->select([
                'odd_mansion.id',
                'odd_mansion.match_id',
                'odd_mansion.variety',
                'odd_mansion.period',
                'odd_mansion.type',
                'odd_mansion.condition',
                'odd_mansion.surebet_value',
                'odd_mansion.status',
                'odd_mansion.created_at',
                'odd_mansion.ready_at',
                'match.match_time',
                'match.team1_id',
                'match.team2_id',
                'match.tournament_id',
                'match.has_score',
                'match.has_period1_score',
                'match.score1',
                'match.score2',
                'match.corner1',
                'match.corner2',
                'match.score1_period1',
                'match.score2_period1',
                'match.corner1_period1',
                'match.corner2_period1',
            ]);
    }

    /**
     * 导出数据列表
     * @param array $data 通过getOddList获取到的数据
     * @param mixed|null $fp
     * @return string
     */
    public function exportOddList(array $data, mixed $fp = null): string
    {
        //构建导出的数据
        $rows = [];

        if (empty($fp)) {
            //如果没有传入文件资源，那么就写入头
            $rows[] = [
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
                '是否推荐',
                '推荐方向',
                '推荐盘口',
                '结果',
                '对应赛果',
            ];
        }

        //生成数据
        foreach ($data as $row) {
            //二次比对规则
            $promoted_text = '';
            $promoted_type = '';
            $promoted_condition = '';
            $result = '';
            $result_score = '';

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
                $promoted_text,
                $promoted_type,
                $promoted_condition,
                $result,
                $result_score,
            ];

            if (!empty($fp)) {
                //如果传入的文件资源，那么以csv的方式写入数据
                fputcsv($fp, $add_row);
            } else {
                $rows[] = $add_row;
            }
        }

        if (!empty($fp)) return '';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($rows, '');

        //输出数据
        $writer = new Xlsx($spreadsheet);
        $filePath = runtime_path() . '/' . uniqid() . '.xlsx';
        $writer->save($filePath);
        return $filePath;
    }
}