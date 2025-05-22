<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\PromotedOdd;
use Carbon\Carbon;

/**
 * 概览面板的业务
 */
class DashboardService
{
    /**
     * 获取概览面板的数据统计
     * @return array
     */
    public function getSummary(): array
    {
        $end = Carbon::today()->addDays();
        $today_start = Carbon::today();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        return [
            'today' => $this->getSummaryByDateRange($today_start),
            'yesterday' => $this->getSummaryByDateRange($today_start->clone()->subDays(), $today_start),
            'days_7' => $this->getSummaryByDateRange($days_7_start),
            'days_30' => $this->getSummaryByDateRange($days_30_start),
            'all' => $this->getSummaryByDateRange(),
        ];
    }

    /**
     * 获取指定日期的概览面板数据统计
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @return array
     */
    public function getSummaryByDateRange(?Carbon $start = null, ?Carbon $end = null): array
    {
        //总推荐数据
        $promoted = PromotedOdd::query()
            ->join('match', 'match.id', '=', 'promoted_odd.match_id')
            ->where('promoted_odd.is_valid', '=', 1)
            ->whereNotNull('promoted_odd.result')
            ->when(isset($start), fn($query) => $query->where('match.match_time', '>=', $start->to))
            ->groupBy('promoted_odd.result')
            ->selectRaw('COUNT(1) AS total')
            ->select(['promoted_odd.result'])
            ->get();
    }
}