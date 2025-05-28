<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Order;
use Carbon\Carbon;

class OrderService
{
    /**
     * 获取订单列表
     * @param array $params
     * @return array
     */
    public function getList(array $params): array
    {
        $query = Order::query()
            ->where('status', '=', 'paid');

        if (isset($params['type'])) {
            $query->where(
                'type',
                '=',
                $params['type'],
            );
        }

        if (!empty($params['start_date'])) {
            $query->where(
                'paid_at',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'paid_at',
                '<',
                Carbon::parse($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        if (!empty($params['extra'])) {
            foreach ($params['extra'] as $key => $value) {
                if (!is_string($key)) continue;
                if (!isset($value)) continue;
                $query->whereRaw("extra ->> ? = ?", [$key, $value]);
            }
        }

        $total = $query->count();

        $list = $query
            ->orderBy('id', 'desc')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get([
                'id',
                'order_number',
                'user_id',
                'type',
                'amount',
                'currency',
                'extra',
                'channel_type',
                'paid_at'
            ])
            ->toArray();

        foreach ($list as $k => $row) {
            $list[$k]['extra'] = !empty($row['extra']) ? json_decode($row['extra'], true) : null;
        }

        return [
            'total' => $total,
            'list' => $list,
        ];
    }
}