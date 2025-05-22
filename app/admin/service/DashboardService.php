<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\PromotedOdd;
use app\model\User;
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
            ->when(isset($start), fn($query) => $query->where('match.match_time', '>=', $start->toISOString()))
            ->when(isset($end), fn($query) => $query->where('match.match_time', '<', $end->toISOString()))
            ->groupBy('promoted_odd.result')
            ->selectRaw('COUNT(1) AS total')
            ->addSelect(['promoted_odd.result'])
            ->get()
            ->toArray();

        $win = 0;
        $loss = 0;
        $draw = 0;
        $win_rate = 0;

        if (!empty($promoted)) {
            $promoted = array_column($promoted, 'total', 'result');
            if (isset($promoted[1])) {
                $win = $promoted[1];
            }
            if (isset($promoted[0])) {
                $draw = $promoted[0];
            }
            if (isset($promoted[-1])) {
                $loss = $promoted[-1];
            }
        }

        $total = $win + $loss;

        if ($total > 0) {
            $win_rate = round($win * 1000 / $total) / 10;
        }

        //用户数
        $user_count = User::query()
            ->when(isset($start), fn($query) => $query->where('created_at', '>=', $start->toISOString()))
            ->when(isset($end), fn($query) => $query->where('created_at', '<', $end->toISOString()))
            ->count();

        return [
            'total' => $total,
            'win' => $win,
            'loss' => $loss,
            'draw' => $draw,
            'win_rate' => $win_rate,
            'user' => $user_count,
        ];
    }
}