<?php declare(strict_types=1);

namespace app\admin\service;

use app\api\service\DataService;
use app\model\LabelPromoted;
use app\model\Order;
use app\model\TournamentLabel;
use app\model\User;
use Carbon\Carbon;
use DI\Attribute\Inject;
use support\Db;

/**
 * 概览面板的业务
 */
class DashboardService
{
    #[Inject]
    protected DataService $dataService;

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
            'today' => $this->dataService->summary(['generic'], $today_start),
            'yesterday' => $this->dataService->summary(['generic'], $today_start->clone()->subDays(), $today_start),
            'days_7' => $this->dataService->summary(['generic'], $days_7_start),
            'days_30' => $this->dataService->summary(['generic'], $days_30_start),
            'all' => $this->dataService->summary(['generic']),
        ];
    }

    /**
     * 获取新老融合推荐统计数据
     * @return array
     */
    public function getV2ToV3Summary(): array
    {
        $today_start = Carbon::today();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        return [
            'today' => $this->dataService->summary(['optimized'], $today_start),
            'yesterday' => $this->dataService->summary(['optimized'], $today_start->clone()->subDays(), $today_start),
            'days_7' => $this->dataService->summary(['optimized'], $days_7_start),
            'days_30' => $this->dataService->summary(['optimized'], $days_30_start),
            'all' => $this->dataService->summary(['optimized']),
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
            ->join('promoted', 'promoted.id', '=', 'label_promoted.promote_id')
            ->join('match', 'match.id', '=', 'promoted.match_id')
            ->when(isset($start), fn($query) => $query->where('match.match_time', '>=', $start->toISOString()))
            ->when(isset($end), fn($query) => $query->where('match.match_time', '<', $end->toISOString()))
            ->groupBy('label_promoted.label_id', 'promoted.result')
            ->selectRaw('COUNT(1) AS total')
            ->addSelect(['label_promoted.label_id', 'promoted.result'])
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

    /**
     * 获取mansion对比统计数据
     * @return array
     */
    public function getMansionSummary(): array
    {
        $today_start = Carbon::today();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        return [
            'today' => $this->dataService->summary(['mansion'], $today_start),
            'yesterday' => $this->dataService->summary(['mansion'], $today_start->clone()->subDays(), $today_start),
            'days_7' => $this->dataService->summary(['mansion'], $days_7_start),
            'days_30' => $this->dataService->summary(['mansion'], $days_30_start),
            'all' => $this->dataService->summary(['mansion']),
        ];
    }
}