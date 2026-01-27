<?php declare(strict_types=1);

namespace support\payment;

use app\api\service\BmissService;
use app\model\Order;
use support\Log;

/**
 * Bmiss钻石支付
 */
class Bmiss extends Engine
{
    /**
     * 获取支付方式类型
     * @return string
     */
    public function getPaymentType(): string
    {
        return 'bmiss';
    }

    /**
     * 创建交易
     * @param Order $order 内部交易订单对象
     * @return array 需要返回给前端的支付数据
     */
    public function create(Order $order): array
    {
        return [
            'amount' => intval($order->amount),
            'out_order_no' => $order->order_number,
        ];
    }

    /**
     * 完成交易
     * @param Order $order 内部交易订单对象
     * @param array $transaction 支付完成数据
     * @return bool
     */
    public function complete(Order $order, array $transaction): bool
    {
        //检查订单信息
        if (($transaction['out_order_no'] ?? '') !== $order->order_number) {
            Log::channel('bmiss')
                ->warning("[订单完成] 订单数据异常 订单号不匹配 order_id=$order->id\n" . json_enc($transaction));
            return false;
        }

        //检查原始交易金额
        if (bccomp((string)$transaction['amount'], $order->amount, 3) !== 0) {
            Log::channel('bmiss')
                ->warning("[订单完成] 订单数据异常 订单金额不匹配 order_id=$order->id\n" . json_enc($transaction));
            return false;
        }

        //写入订单完成数据
        $order->channel_order_no = $transaction['order_no'];
        $order->channel_order_info = json_encode($transaction);

        Log::channel('bmiss')
            ->warning("[订单完成] order_id=$order->id\n" . json_enc($transaction));

        return true;
    }

    /**
     * 通过订单查询外部交易数据
     * @param Order $order
     * @return array
     */
    public function check(Order $order): array
    {
        $ret = G(BmissService::class)->api('/mini/query_consume', [
            'out_order_no' => $order->order_number,
        ]);
        if ($ret['code'] !== 0) return [];
        return $ret['data'];
    }

    /**
     * 校验交易回调数据
     * @param array $post
     * @return bool
     */
    public function verifyCallback(array $post, array $get): bool
    {
        return true;
    }
}