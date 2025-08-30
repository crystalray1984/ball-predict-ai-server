<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\SurebetRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SurebetRecordService
{
    /**
     * 创建查询对象
     * @param array $params
     * @return Builder
     */
    public function createQuery(array $params): Builder
    {
        $query = SurebetRecord::query()
            ->join('v_match', 'v_match.crown_match_id', '=', 'surebet_record.crown_match_id');

        if (!empty($params['game'])) {
            $query->where('surebet_record.game', '=', $params['game']);
        }
        if (!empty($params['base'])) {
            $query->where('surebet_record.base', '=', $params['base']);
        }
        if (!empty($params['variety'])) {
            $query->where('surebet_record.variety', '=', $params['variety']);
        }
        if (!empty($params['type'])) {
            $query->where('surebet_record.type', '=', $params['type']);
        }

        if (!empty($params['match_date_start'])) {
            $query->where('v_match.match_time', '>=', Carbon::parse($params['match_date_start'])->toISOString());
        }

        if (!empty($params['match_date_end'])) {
            $query->where('v_match.match_time', '<', Carbon::parse($params['match_date_end'])->addDay()->toISOString());
        }

        if (!empty($params['date_start'])) {
            $query->where('surebet_record.created_at', '>=', Carbon::parse($params['date_start'])->toISOString());
        }

        if (!empty($params['date_end'])) {
            $query->where('surebet_record.created_at', '<', Carbon::parse($params['date_end'])->addDay()->toISOString());
        }

        return $query
            ->orderBy('surebet_record.id', 'DESC')
            ->select([
                'surebet_record.*',
                'v_match.id AS match_id',
                'v_match.match_time',
                'v_match.tournament_id',
                'v_match.tournament_name',
                'v_match.team1_id',
                'v_match.team1_name',
                'v_match.team2_id',
                'v_match.team2_name',
                'v_match.has_score',
                'v_match.has_period1_score',
                'v_match.score1',
                'v_match.score2',
                'v_match.corner1',
                'v_match.corner2',
                'v_match.score1_period1',
                'v_match.score2_period1',
                'v_match.corner1_period1',
                'v_match.corner2_period1',
            ]);
    }

    /**
     * 查询推送数据列表
     * @param array $params
     * @return array
     */
    public function getList(array $params): array
    {
        $query = $this->createQuery($params);
        $count = $query->count();
        $list = $query->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get()
            ->toArray();

        //构建数据结构
        $list = array_map(function (array $row) {
            //构建模拟盘口
            $virtual_odd = [];
            if (
                $row['game'] === 'regular'
                && $row['base'] === 'overall'
                && in_array($row['variety'], ['corner', 'goal'])
                && in_array($row['period'], ['regularTime', 'period1'])
                && in_array($row['type'], ['ah1', 'ah2', 'over', 'under'])
            ) {
                if (
                    $row['period'] === 'period1' && $row['has_period1_score'] ||
                    $row['period'] === 'regularTime' && $row['has_score']
                ) {
                    //虚拟盘口数据
                    [$type, $condition] = get_reverse_odd($row['type'], $row['condition']);

                    $virtual_odd = [
                        'variety' => $row['variety'],
                        'period' => $row['period'],
                        'type' => $type,
                        'condition' => $condition,
                    ];

                    $result = get_odd_score($row, $virtual_odd);
                    $virtual_odd['score'] = $result['score'];
                    $virtual_odd['result'] = $result['result'];
                }
            }

            return [
                ...$row,
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
                'virtual_odd' => !empty($virtual_odd) ? $virtual_odd : null,
            ];
        }, $list);

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 导出数据
     * @param array $params
     * @return string
     */
    public function exportList(array $params): string
    {
        //构建导出的数据
        $rows = [
            [
                '推送id',
                '比赛id',
                '联赛',
                '比赛时间',
                '主队',
                '客队',
                'game',
                'base',
                '时段',
                '玩法',
                '方向',
                '盘口',
                '推送水位',
                '推送时间',
                '模拟盘口(反推)',
                '模拟盘口结果',
                '模拟盘口赛果',
            ]
        ];

        //查询数据
        $this->createQuery($params)
            ->chunk(500, function ($_list) use (&$rows) {
                $list = $_list->toArray();
                foreach ($list as $row) {
                    //看看有没有对应的赛果(只针对支持的投注类型)
                    $virtual_odd = [];

                    if (
                        $row['game'] === 'regular'
                        && $row['base'] === 'overall'
                        && in_array($row['variety'], ['corner', 'goal'])
                        && in_array($row['period'], ['regularTime', 'period1'])
                        && in_array($row['type'], ['ah1', 'ah2', 'over', 'under'])
                    ) {
                        if (
                            $row['period'] === 'period1' && $row['has_period1_score'] ||
                            $row['period'] === 'regularTime' && $row['has_score']
                        ) {
                            //虚拟盘口数据
                            [$type, $condition] = get_reverse_odd($row['type'], $row['condition']);

                            $virtual_odd = [
                                'variety' => $row['variety'],
                                'period' => $row['period'],
                                'type' => $type,
                                'condition' => $condition,
                            ];

                            $result = get_odd_score($row, $virtual_odd);
                            $virtual_odd['score'] = $result['score'];
                            $virtual_odd['result'] = $result['result'];
                        }
                    }


                    //模拟盘口数据
                    $virtual_text = '';
                    $virtual_result = '';
                    $virtual_score = '';

                    if (!empty($virtual_odd)) {
                        $virtual_type = match ($virtual_odd['type']) {
                            'ah1' => '主胜',
                            'ah2' => '客胜',
                            'over' => '大球',
                            'under' => '小球',
                            default => '',
                        };
                        $virtual_condition = match ($virtual_odd['type']) {
                            'ah1', 'ah2' => (bccomp($virtual_odd['condition'], '0', 2) > 0 ? '+' : '') . (float)$virtual_odd['condition'],
                            default => (string)(float)$virtual_odd['condition'],
                        };

                        $virtual_text = "$virtual_type $virtual_condition";
                        $virtual_result = match ($virtual_odd['result']) {
                            0 => '和',
                            1 => '赢',
                            -1 => '输',
                            default => '',
                        };
                        $virtual_score = $virtual_odd['score'];
                    }

                    //写入数据
                    $rows[] = [
                        $row['id'],
                        $row['match_id'],
                        $row['tournament_name'],
                        Carbon::parse($row['match_time'])->toDateTimeString(),
                        $row['team1_name'],
                        $row['team2_name'],
                        $row['game'],
                        $row['base'],
                        $row['period'],
                        $row['variety'],
                        $row['type'],
                        $row['condition'],
                        $row['value'],
                        Carbon::parse($row['created_at'])->toDateTimeString(),
                        $virtual_text,
                        $virtual_result,
                        $virtual_score,
                    ];
                }
            });

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