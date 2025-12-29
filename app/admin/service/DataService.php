<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\RockBallOdd;
use Illuminate\Database\Query\JoinClause;

/**
 * 数据统计服务
 */
class DataService
{
    /**
     * 滚球数据统计查询
     * @param array $params
     * @return array
     */
    public function rockballSummary(array $params): array
    {
        $query = RockBallOdd::query()
            ->join('promoted', function (JoinClause $join) {
                $join->on('promoted.source_id', '=', 'rockball_odd.id')
                    ->where('promoted.source_type', '=', 'rockball');
            })
            ->where('rockball_odd.source_period', '=', $params['period'])
            ->where('rockball_odd.source_type', '=', $params['type'])
            ->whereNotNull('promoted.result');

        if (isset($params['condition2'])) {
            $query->whereBetween('rockball_odd.source_condition', [$params['condition'], $params['condition2']]);
        } else {
            $query->where('rockball_odd.source_condition', '=', $params['condition']);
        }

        $list = $query
            ->orderBy('promoted.period')
            ->orderBy('promoted.condition')
            ->orderBy('promoted.type')
            ->select([
                'promoted.period',
                'promoted.type',
                'promoted.condition',
                'promoted.value',
                'promoted.result',
                'promoted.score1',
                'promoted.score2',
            ])
            ->get()
            ->toArray();

        $result = [];
        //整理统计
        foreach ($list as $item) {
            $condition = (string)(float)$item['condition'];
            $key = implode(':', [$item['variety'], $item['period'], $condition, $item['type']]);

            if (!isset($result[$key])) {
                $result[$key] = [
                    'period' => $item['period'],
                    'type' => $item['type'],
                    'condition' => $condition,
                    'win' => 0,
                    'win_half' => 0,
                    'win_all' => 0,
                    'loss' => 0,
                    'draw' => 0,
                    'profit' => '0',
                ];
            }

            switch ($item['result']) {
                case 1:
                    //赢
                    $result[$key]['win']++;
                    break;
                case -1:
                    //输
                    $result[$key]['loss']++;
                    break;
                case 0:
                    $result[$key]['draw']++;
                    break;
            }
            [$profit, $win_count] = get_odd_profit($item);
            if ($win_count === 2) {
                $result[$key]['win_all']++;
            } else if ($win_count === 1) {
                $result[$key]['win_half']++;
            }
            $result[$key]['profit'] = bcadd($result[$key]['profit'], $profit, 6);
        }

        //统计胜率
        array_walk($result, function (&$item) {
            $win = $item['win'] + $item['half_win'];
            $total = $win + $item['loss'];
            if ($total > 0) {
                $item['win_rate'] = $win * 100 / $total;
            } else {
                $item['win_rate'] = 0;
            }
        });

        return $result;
    }
}