<?php declare(strict_types=1);

namespace support\payment;

use app\model\Order;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use support\Log;

/**
 * Endless正式链支付引擎
 */
class Endless extends Engine
{
    public string $incomeAddress;

    public string $apiUrl;

    public function __construct()
    {
        //读取配置
        $config = config('payment_endless');
        $this->incomeAddress = $config['income_address'];
        $this->apiUrl = $config['api_url'];
    }

    /**
     * 通过hash查询链上的交易信息
     * @param string $hash
     * @return array
     * @throws GuzzleException
     */
    protected function getTransaction(string $hash): array
    {
        $client = new Client();
        $retry = 10;
        while (true) {
            try {
                $resp = $client->get(
                    $this->apiUrl . '/v1/transactions/by_hash/' . $hash,
                    [
                        'timeout' => 10,
                    ]
                );
                if ($resp->getStatusCode() === 404) {
                    return [];
                }
                if ($resp->getStatusCode() === 200) {
                    Log::channel('endless')->info('[交易信息] ' . $hash);
                    $contents = $resp->getBody()->getContents();
                    Log::channel('endless')->info($contents);
                    return json_decode($contents, true);
                }
            } catch (GuzzleException $e) {
                $retry--;
                if ($retry > 0) {
                    Log::channel('endless')->error($e);
                    sleep(5);
                    continue;
                }
                throw $e;
            }
        }
    }

    public function getPaymentType(): string
    {
        return 'luffa';
    }

    /**
     * 创建交易
     * @param Order $order 内部交易订单对象
     * @return array
     */
    public function create(Order $order): array
    {
        $order->channel_id = $this->incomeAddress;

        return [
            '1_address_address' => $this->incomeAddress,
            '2_u128_amount' => bcmul((string)$order->amount, '100000000', 0),
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
        if (($transaction['hash'] ?? '') !== $order->channel_order_no) {
            Log::channel('endless')
                ->warning("[Luffa订单完成] 订单数据异常 hash不匹配 order_id=$order->id hash={$order->channel_order_no}\n" . json_enc($transaction));
            return false;
        }

        //检查完成状态
        if (($transaction['success'] ?? false) !== true) {
            Log::channel('endless')
                ->warning("[Luffa订单完成] 订单数据异常 success不正确 order_id=$order->id hash={$order->channel_order_no}\n" . json_enc($transaction));
            return false;
        }

        //寻找交易信息中的收款人与订单信息是否相符
        if (isset($transaction['events']) && is_array($transaction['events'])) {
            foreach ($transaction['events'] as $event) {
                if ($event['type'] === '0x1::fungible_asset::Deposit') {
                    if (
                        $event['data']['owner'] === $order->channel_id &&
                        bccomp($event['data']['amount'], bcmul((string)$order->amount, '100000000', 0), 0) === 0
                    ) {
                        //收款信息相符，写入订单完成数据
                        $order->channel_order_info = json_enc($transaction);
                        return true;
                    }
                }
            }
        }

        //订单校验失败
        Log::channel('endless')
            ->warning("[Luffa订单完成]订单数据异常 未找到收款信息 order_id=$order->id hash={$order->channel_order_no}\n" . json_enc($transaction));
        return false;
    }

    /**
     * 通过订单查询外部交易数据
     * @param Order $order
     * @return array
     * @throws GuzzleException
     */
    public function check(Order $order): array
    {
        if (empty($order->channel_order_no)) return [];
        return $this->getTransaction($order->channel_order_no);
    }

    /**
     * 校验交易回调数据
     * @param array $post
     * @return bool
     */
    public function verifyCallback(array $post): bool
    {
        return !empty($post['hash']) && is_string($post['hash']);
    }
}