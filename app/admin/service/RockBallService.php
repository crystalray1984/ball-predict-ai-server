<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\RockBallOdd;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 滚球盘业务逻辑
 */
class RockBallService
{
    public function createQuery(array $params): Builder
    {
        $query = RockBallOdd::query()
            ->join('v_match', 'v_match.id', '=', 'rockball_odd.match_id')
            ->leftJoin('promoted', function (JoinClause $join) {
                $join->on('promoted.source_id', '=', 'rockball_odd.id')
                    ->where('promoted.source_type', '=', 'rockball');
            });

        if (!empty($params['start_date'])) {
            $query->where(
                'v_match.match_time',
                '>=',
                crown_time($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'v_match.match_time',
                '<',
                crown_time($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        if (isset($params['promote'])) {
            switch ($params['promote']) {
                case 0:
                    $query->whereNull('promoted.id');
                    break;
                case 1:
                    $query->whereNotNull('promoted.id');
                    break;
            }
        }

        if (!empty($params['auto_hide'])) {
            $query->where(function ($query) {
                $query->whereNotNull('promoted.id')
                    ->orWhere('v_match.match_time', '>', Carbon::now()->subHours(2)->toISOString());
            });
        }

        if (isset($params['order'])) {
            if ($params['order'] === 'match_time') {
                $query->orderBy('v_match.match_time', 'DESC');
            } else if ($params['order'] === 'promote_time') {
                $query->orderBy('promoted.id', 'DESC');
            }
        }
        $query->orderBy('rockball_odd.id', 'DESC');

        $query->select([
            'rockball_odd.*',
            'v_match.match_time',
            'v_match.team1_name',
            'v_match.team2_name',
            'v_match.tournament_name',
            'promoted.is_valid',
            'promoted.value AS promoted_value',
            'promoted.result',
            'promoted.score',
            'promoted.created_at AS promoted_at',
        ]);

        return $query;
    }

    public function getOddList(array $params): array
    {
        return $this->createQuery($params)
            ->get()
            ->toArray();
    }

    public function setIsOpen(int $id, int $is_open): void
    {
        RockBallOdd::query()
            ->where('id', '=', $id)
            ->update(['is_open' => $is_open]);
    }

    public function exportList(array $params): string
    {
        $excel = new Spreadsheet();
        $sheet = $excel->getActiveSheet();

        //写入表头
        $sheet->fromArray([
            '比赛时间',
            '赛事',
            '主队',
            '客队',
            '触发条件-时段',
            '触发条件-玩法',
            '触发条件-方向',
            '触发条件-盘口',
            '触发条件-水位',
            '匹配时间',
            '追踪盘口-时段',
            '追踪盘口-玩法',
            '追踪盘口-方向',
            '追踪盘口-盘口',
            '追踪盘口-水位条件',
            '推荐开启',
            '推荐水位',
            '推荐时间',
            '赛果',
            '输赢'
        ]);

        $rowIndex = 2;

        $this->createQuery($params)
            ->chunk(100, function ($list) use (&$sheet, &$rowIndex) {
                $list = $list->toArray();

                foreach ($list as $match) {
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
                        $match['team1_name'],   //主队
                        $match['team2_name'],   //客队


                        //触发条件-时段
                        get_period_text($match['source_period']),
                        //触发条件-玩法
                        get_variety_text($match['source_variety']),
                        //触发条件-方向
                        get_odd_type_text($match['source_type']),
                        //触发条件-盘口
                        get_condition_text($match['source_condition'], $match['source_type']),
                        //触发条件-水位
                        (float)$match['source_value'],

                        //匹配时间
                        Carbon::parse($match['created_at'])->format('Y-m-d H:i:s'),

                        //追踪盘口-时段
                        get_period_text($match['period']),
                        //追踪盘口-玩法
                        get_variety_text($match['variety']),
                        //追踪盘口-方向
                        get_odd_type_text($match['type']),
                        //追踪盘口-盘口
                        get_condition_text($match['condition'], $match['type']),
                        //追踪盘口-水位
                        (float)$match['value'],

                        //是否开启推荐
                        $match['is_open'] ? '开启' : '关闭',

                        //推荐水位
                        isset($match['promoted_value']) ? (float)$match['promoted_value'] : '',
                        //推荐时间
                        isset($match['promoted_at']) ? Carbon::parse($match['promoted_at'])->format('Y-m-d H:i:s') : '',
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