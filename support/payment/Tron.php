<?php declare(strict_types=1);

namespace support\payment;

use app\model\Order;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Plisio\ClientAPI;
use support\payment\trait\Plisio;

/**
 * 波场Tron支付引擎
 */
class Tron extends Engine
{
    use Plisio;

    public function __construct()
    {
        $config = config('payment_plisio');
        $this->email = $config['email'];
        $this->secret = $config['secret'];
    }

    /**
     * 获取支付方式类型
     * @return string
     */
    public function getPaymentType(): string
    {
        return 'web';
    }

    /**
     * 创建交易
     * @param Order $order 内部交易订单对象
     * @return array 需要返回给前端的支付数据
     * @throws GuzzleException
     */
    public function create(Order $order): array
    {
        //创建订单数据
        $invoice_data = [
            'order_number' => $order->order_number,
            'order_name' => $order->type,
            'amount' => $order->amount,
            'currency' => 'USDT_TRX',
            'allowed_psys_cids' => 'USDT_TRX',
            'email' => $this->email,
        ];

        $transaction = $this->createInvoice($invoice_data);

        //写入订单数据
        $order->channel_id = $this->email;
        $order->channel_order_no = $transaction['data']['txn_id'];

        return [
            'url' => $transaction['data']['invoice_url'],
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
        if ($transaction['status'] !== 'success') {
            return false;
        }

        //比对订单号
        if ($transaction['data']['id'] !== $order->channel_order_no) {
            return false;
        }

        //比对订单状态
        if ($transaction['data']['status'] !== 'completed') {
            return false;
        }

        //比对订单类型
        if ($transaction['data']['type'] !== 'invoice') {
            return false;
        }

        //写入订单数据
        $order->channel_order_info = json_enc($transaction['data']);

        return true;
    }

    /**
     * 通过订单查询外部交易数据
     * @param Order $order
     * @return array
     * @throws GuzzleException
     */
    public function check(Order $order): array
    {
        return $this->getTransaction($order->channel_order_no);
    }
}