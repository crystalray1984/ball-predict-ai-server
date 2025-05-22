<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;

class OrderService
{
    /**
     * 创建Luffa订单
     * @return void
     */
    public function createLuffaOrder(int $user_id, string $network, string $type): array
    {
        $config = config('payment');
        $config = $config[$network];

        //订单信息
        $order_info = $config['config'][$type];

        //创建订单信息
        $order = new Order();
        $order->user_id = $user_id;
        $order->type = 1;
        $order->amount = $order_info['price'];
        $order->currency = 'EDS';
        $order->channel_id = $config['income_address'];
        $order->channel = $network;
        $order->extra = json_enc($order_info);
        $order->save();

        return [
            'order_id' => $order->id,
            'payment_data' => [
                '1_address_address' => $config['income_address'],
                '2_u128_amount' => bcmul((string)$order->amount, '100000000', 0),
            ]
        ];
    }
}