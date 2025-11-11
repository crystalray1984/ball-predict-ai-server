<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\SurebetV2Promoted;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SurebetV2Service
{
    protected function createQuery(array $params): Builder
    {
        $query = SurebetV2Promoted::query()
            ->join('v_match', 'v_match.id', '=', 'surebet_v2_promoted.match_id');

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

        if (isset($params['label_id'])) {
            $query->where('v_match.tournament_label_id', '=', $params['label_id']);
        }

        if ($params['order'] === 'match_time') {
            $query->orderBy('v_match.match_time', 'DESC');
        } else {
            $query->orderBy('surebet_v2_promoted.id', 'DESC');
        }

        $query->select([
            'surebet_v2_promoted.*',
            'v_match.match_time',
            'v_match.tournament_id',
            'v_match.tournament_name',
            'v_match.team1_id',
            'v_match.team1_name',
            'v_match.team2_id',
            'v_match.team2_name',
            'v_match.tournament_label_id',
            'v_match.tournament_label_title',
        ]);

        return $query;
    }

    public function getList(array $params): array
    {
        $query = $this->createQuery($params);

        $list = $query->get()
            ->toArray();

        return array_map(function (array $row) {
            $result = [
                ...$row,
                'team1' => [
                    'id' => $row['team1_id'],
                    'name' => $row['team1_name'],
                ],
                'team2' => [
                    'id' => $row['team2_id'],
                    'name' => $row['team2_name'],
                ],
                'tournament' => [
                    'id' => $row['tournament_id'],
                    'name' => $row['tournament_name'],
                ],
                'label' => !empty($row['tournament_label_id']) ? [
                    'id' => $row['tournament_label_id'],
                    'title' => $row['tournament_label_title'],
                ] : null,
            ];

            unset(
                $result['team1_id'],
                $result['team1_name'],
                $result['team2_id'],
                $result['team2_name'],
                $result['tournament_id'],
                $result['tournament_name'],
                $result['tournament_label_id'],
                $result['tournament_label_title'],
            );

            return $result;
        }, $list);
    }

    public function exportList(array $params): string
    {
        $excel = new Spreadsheet();
        $sheet = $excel->getActiveSheet();

        //写入表头
        $sheet->fromArray([
            '比赛时间',
            '赛事',
            '标签',
            '主队',
            '客队',
            '类型',
            '正反推',
            '推荐方向',
            '推荐盘口',
            '推送水位',
            '赛果',
            '输赢'
        ]);

        $rowIndex = 2;

        $this->createQuery($params)
            ->chunk(100, function ($list) use (&$sheet, &$rowIndex) {
                $list = $list->toArray();
                foreach ($list as $match) {
                    $condition = floatval($match['condition']);

                    //赛果
                    if (isset($match['result'])) {
                        //赛果
                        $score = $match['score'];
                        //输赢
                        $result = match ($match['result']) {
                            1 => '赢',
                            -1 => '输',
                            0 => '和'
                        };
                    } else {
                        //赛果
                        $score = '';
                        //输赢
                        $result = '';
                    }

                    $row = [
                        Carbon::parse($match['match_time'])->format('Y-m-d H:i:s'), //比赛时间
                        $match['tournament_name'],  //赛事
                        $match['tournament_label_title'] ?? '', //标签
                        $match['team1_name'],   //主队
                        $match['team2_name'],   //客队

                        //类型
                        match ($match['odd_type']) {
                            'ah' => '让球',
                            'sum' => '大小球',
                            default => '',
                        },

                        //正反推
                        $match['back'] ? '反推' : '正推',

                        //推荐方向
                        match ($match['type']) {
                            'ah1' => '主队',
                            'ah2' => '客队',
                            'under' => '小球',
                            'over' => '大球',
                            default => '',
                        },

                        //推荐盘口
                        match ($match['type']) {
                            'ah1', 'ah2' => $condition <= 0 ? strval($condition) : "+$condition",
                            default => $condition,
                        },

                        //水位
                        floatval($match['value']),

                        //推荐时间
                        Carbon::parse($match['created_at'])->format('Y-m-d H:i:s'),

                        //赛果
                        $score,

                        //输赢
                        $result,
                    ];

                    //写入数据
                    $sheet->fromArray($row, startCell: "A$rowIndex");
                    $rowIndex++;
                }
            });

        //创建导出文件
        $writer = new Xlsx($excel);
        $filePath = runtime_path() . '/' . uniqid() . '.xlsx';
        $writer->save($filePath);

        return $filePath;
    }
}