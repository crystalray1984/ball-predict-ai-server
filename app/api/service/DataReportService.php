<?php declare(strict_types=1);

namespace app\api\service;

use app\model\PromotedView;
use Carbon\Carbon;
use support\Redis;

/**
 * 数据统计逻辑
 */
class DataReportService
{
    /**
     * 生成统计报告
     * @param string[] $channels
     * @param Carbon|string $start
     * @param Carbon|string $end
     * @return array
     */
    protected function createReport(array $channels, Carbon|string $start, Carbon|string $end): array
    {
        $list = PromotedView::query()
            ->whereIn('channel', $channels)
            ->where('match_time', '>=', $start->toISOString())
            ->where('match_time', '<', $end->toISOString())
            ->whereNotNull('result')
            ->whereNotNull('value')
            ->get([
                'variety',
                'period',
                'type',
                'condition',
                'value',
                'result',
            ])
            ->toArray();

        $output = [];
        foreach ($list as $item) {
            $condition = (string)(float)$item['condition'];
            $key = implode(':', [$item['variety'], $item['period'], $condition, $item['type']]);
            if (!isset($output[$key])) {
                $output[$key] = [
                    'variety' => $item['variety'],
                    'period' => $item['period'],
                    'type' => $item['type'],
                    'condition' => $condition,
                    'win' => 0,
                    'loss' => 0,
                    'draw' => 0,
                    'profit' => '0'
                ];
            }
            switch ($item['result']) {
                case 1:
                    //赢
                    $output[$key]['win']++;
                    $output[$key]['profit'] = bcadd($output[$key]['profit'], bcsub($item['value'], '1', 6), 6);
                    break;
                case -1:
                    //输
                    $output[$key]['loss']++;
                    $output[$key]['profit'] = bcsub($output[$key]['profit'], '1', 6);
                    break;
                case 0:
                    $output[$key]['draw']++;
                    break;
            }
        }

        return array_values($output);
    }

    /**
     * 按周获取统计报告
     * @param string[] $channels
     * @param int $week
     * @param bool $force
     * @return array
     */
    public function getReport(array $channels, int $week, bool $force = false): array
    {
        //周起点
        $week_start = Carbon::now()->startOf('week')->subDays($week * 7);
        $week_end = $week_start->clone()->addDays(7);
        $week_day = (int)$week_start->format('Ymd');

        //看看有没有缓存数据
        $key = "report:$week_day:" . implode('|', $channels);
        if (!$force) {
            $cache = Redis::get($key);
            if (!empty($cache)) {
                return [
                    'start' => $week_start,
                    'end' => $week_end->subMillisecond(),
                    'data' => json_decode($cache, true)
                ];
            }
        }

        $data = $this->createReport($channels, $week_start, $week_end);
        Redis::setEx($key, 86400 * 14, json_enc($data));
        return [
            'start' => $week_start,
            'end' => $week_end->subMillisecond(),
            'data' => $data
        ];
    }
}