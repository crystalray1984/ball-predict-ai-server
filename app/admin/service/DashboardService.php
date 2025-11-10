<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\LabelPromoted;
use app\model\Order;
use app\model\PromotedOdd;
use app\model\SurebetV2Promoted;
use app\model\TournamentLabel;
use app\model\User;
use Carbon\Carbon;
use support\Db;

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
            ->when(isset($start), fn($query) => $query->where('match.match_time', '>=', $start->clone()->addHours(12)->toISOString()))
            ->when(isset($end), fn($query) => $query->where('match.match_time', '<', $end->clone()->addHours(12)->toISOString()))
            ->groupBy('promoted_odd.result')
            ->selectRaw('COUNT(1) AS total')
            ->addSelect(['promoted_odd.result'])
            ->get()
            ->toArray();

        $win = 0;
        $loss = 0;
        $draw = 0;
        $win_rate = 0;
        $all = 0;

        if (!empty($promoted)) {
            foreach ($promoted as $row) {
                $all += $row['total'];
                if ($row['result'] === 1) {
                    $win += $row['total'];
                } else if ($row['result'] === -1) {
                    $loss += $row['total'];
                } else if ($row['result'] === 0) {
                    $draw += $row['total'];
                }
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
            'total' => $all,
            'win' => $win,
            'loss' => $loss,
            'draw' => $draw,
            'win_rate' => $win_rate,
            'user' => $user_count,
        ];
    }

    public function getV2ToV3Summary(): array
    {
        $today_start = Carbon::today();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        return [
            'today' => $this->getV2ToV3SummaryByDateRange($today_start),
            'yesterday' => $this->getV2ToV3SummaryByDateRange($today_start->clone()->subDays(), $today_start),
            'days_7' => $this->getV2ToV3SummaryByDateRange($days_7_start),
            'days_30' => $this->getV2ToV3SummaryByDateRange($days_30_start),
            'all' => $this->getV2ToV3SummaryByDateRange(),
        ];
    }

    /**
     * 获取指定日期的概览面板数据统计
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @return array
     */
    public function getV2ToV3SummaryByDateRange(?Carbon $start = null, ?Carbon $end = null): array
    {
        //总推荐数据
        $promoted = SurebetV2Promoted::query()
            ->join('match', 'match.id', '=', 'promoted_odd.match_id')
            ->where('promoted_odd.is_valid', '=', 1)
            ->when(isset($start), fn($query) => $query->where('match.match_time', '>=', $start->clone()->addHours(12)->toISOString()))
            ->when(isset($end), fn($query) => $query->where('match.match_time', '<', $end->clone()->addHours(12)->toISOString()))
            ->groupBy('promoted_odd.result')
            ->selectRaw('COUNT(1) AS total')
            ->addSelect(['promoted_odd.result'])
            ->get()
            ->toArray();

        $win = 0;
        $loss = 0;
        $draw = 0;
        $win_rate = 0;
        $all = 0;

        if (!empty($promoted)) {
            foreach ($promoted as $row) {
                $all += $row['total'];
                if ($row['result'] === 1) {
                    $win += $row['total'];
                } else if ($row['result'] === -1) {
                    $loss += $row['total'];
                } else if ($row['result'] === 0) {
                    $draw += $row['total'];
                }
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
            'total' => $all,
            'win' => $win,
            'loss' => $loss,
            'draw' => $draw,
            'win_rate' => $win_rate,
            'user' => $user_count,
        ];
    }

    /**
     * 获取用户统计
     * @return array
     */
    public function getUserSummary(): array
    {
        $today_start = Carbon::today();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        return [
            'users' => [
                'today' => $this->getUserSummaryByDateRange($today_start),
                'yesterday' => $this->getUserSummaryByDateRange($today_start->clone()->subDays(), $today_start),
                'days_7' => $this->getUserSummaryByDateRange($days_7_start),
                'days_30' => $this->getUserSummaryByDateRange($days_30_start),
                'all' => $this->getUserSummaryByDateRange(),
            ],
            'vip' => $this->getVipSummary(),
        ];
    }

    public function getUserSummaryByDateRange(?Carbon $start = null, ?Carbon $end = null): int
    {
        //用户数
        return User::query()
            ->when(isset($start), fn($query) => $query->where('created_at', '>=', $start->toISOString()))
            ->when(isset($end), fn($query) => $query->where('created_at', '<', $end->toISOString()))
            ->count();
    }

    /**
     * 获取VIP统计数据
     * @return array
     */
    public function getVipSummary(): array
    {
        $today_start = Carbon::today();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        return [
            'today' => $this->getVipSummaryByDateRange($today_start),
            'yesterday' => $this->getVipSummaryByDateRange($today_start->clone()->subDays(), $today_start),
            'days_7' => $this->getVipSummaryByDateRange($days_7_start),
            'days_30' => $this->getVipSummaryByDateRange($days_30_start),
            'all' => $this->getVipSummaryByDateRange(),
        ];
    }

    /**
     * 获取VIP统计数据
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @return array
     */
    public function getVipSummaryByDateRange(?Carbon $start = null, ?Carbon $end = null): array
    {
        $list = Db::table(
            Order::getQuery()
                ->where('status', '=', 'paid')
                ->where('type', '=', 'vip')
                ->when(isset($start), fn($query) => $query->where('paid_at', '>=', $start->toISOString()))
                ->when(isset($end), fn($query) => $query->where('paid_at', '<', $end->toISOString()))
                ->selectRaw("extra ->> 'type' AS type"),
            'a'
        )
            ->groupBy('a.type')
            ->selectRaw('COUNT(1) AS total')
            ->addSelect('a.type')
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();

        $list = array_column($list, 'total', 'type');

        return [
            'day' => $list['day'] ?? 0,
            'week' => $list['week'] ?? 0,
            'month' => $list['month'] ?? 0,
            'quarter' => $list['quarter'] ?? 0,
        ];
    }

    /**
     * 获取标签数据统计
     * @return array
     */
    public function getLabelSummary(): array
    {
        $today_start = Carbon::today();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        $today = $this->getLabelSummaryByRange($today_start);
        $yesterday = $this->getLabelSummaryByRange($today_start->clone()->subDays(), $today_start);
        $days_7 = $this->getLabelSummaryByRange($days_7_start);
        $days_30 = $this->getLabelSummaryByRange($days_30_start);
        $all = $this->getLabelSummaryByRange();

        $labels = TournamentLabel::query()
            ->get(['id', 'title'])
            ->toArray();

        $empty = [
            'all' => 0,
            'win' => 0,
            'loss' => 0,
            'draw' => 0,
            'win_rate' => 0,
        ];

        return array_map(function (array $label) use ($today, $yesterday, $days_7, $days_30, $all, $empty) {
            return [
                'id' => $label['id'],
                'title' => $label['title'],
                'today' => $today[$label['id']] ?? $empty,
                'yesterday' => $yesterday[$label['id']] ?? $empty,
                'days_7' => $days_7[$label['id']] ?? $empty,
                'days_30' => $days_30[$label['id']] ?? $empty,
                'all' => $all[$label['id']] ?? $empty,
            ];
        }, $labels);
    }

    public function getLabelSummaryByRange(?Carbon $start = null, ?Carbon $end = null): array
    {
        //总推荐数据
        $promoted = LabelPromoted::query()
            ->join('promoted_odd', 'promoted_odd.id', '=', 'label_promoted.promote_id')
            ->join('match', 'match.id', '=', 'promoted_odd.match_id')
            ->when(isset($start), fn($query) => $query->where('match.match_time', '>=', $start->clone()->addHours(12)->toISOString()))
            ->when(isset($end), fn($query) => $query->where('match.match_time', '<', $end->clone()->addHours(12)->toISOString()))
            ->groupBy('label_promoted.label_id', 'promoted_odd.result')
            ->selectRaw('COUNT(1) AS total')
            ->addSelect(['label_promoted.label_id', 'promoted_odd.result'])
            ->get()
            ->toArray();

        $result = [];
        foreach ($promoted as $row) {
            $label_id = $row['label_id'];
            if (!isset($result[$label_id])) {
                $result[$label_id] = [
                    'all' => 0,
                    'win' => 0,
                    'loss' => 0,
                    'draw' => 0,
                ];
            }

            $result[$label_id]['all'] += $row['total'];
            if ($row['result'] === 1) {
                $result[$label_id]['win'] += $row['total'];
            } else if ($row['result'] === -1) {
                $result[$label_id]['loss'] += $row['total'];
            } else if ($row['result'] === 0) {
                $result[$label_id]['draw'] += $row['total'];
            }
        }

        foreach ($result as $label_id => $item) {
            $total = $item['win'] + $item['loss'];
            $result[$label_id]['win_rate'] = $total > 0 ? round($item['win'] * 1000 / $total) / 10 : 0;
        }

        return $result;
    }
}