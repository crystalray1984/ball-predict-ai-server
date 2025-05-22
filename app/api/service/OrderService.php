<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;
use Carbon\Carbon;
use support\Db;
use support\Endless;
use support\exception\BusinessError;
use support\Log;
use Throwable;

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

    /**
     * 完成luffa订单
     * @param int $order_id
     * @param string $hash
     * @return void
     */
    public function completeLuffaOrder(int $order_id, string $hash): void
    {
        //首先检查订单是否存在
        $order = Order::query()
            ->where('id', $order_id)
            ->first();
        if (!$order) {
            throw new BusinessError('订单不存在');
        }
        if ($order->status === 1) {
            //订单已完成
            return;
        }

        //判断订单类型必须是endless或者eds
        if ($order->channel !== 'endless' && $order->channel !== 'eds') {
            throw new BusinessError('订单类型错误');
        }

        //调用接口检查订单是否完成
        $transaction = Endless::create($order->network)->getTransaction($hash);
        if (empty($transaction)) {
            //订单未支付
            throw new BusinessError('订单未支付');
        }

        //检查订单信息
        if (($transaction['hash'] ?? '') !== $hash) {
            Log::channel('important')
                ->warning("订单数据异常 hash不匹配 order_id=$order_id hash=$hash\n" . json_enc($transaction));
            throw new BusinessError('订单数据异常');
        }

        if (($transaction['success'] ?? false) !== true) {
            Log::channel('important')
                ->warning("订单数据异常 success不正确 order_id=$order_id hash=$hash\n" . json_enc($transaction));
            throw new BusinessError('订单数据异常 支付未完成');
        }

        //寻找交易信息中的收款人与订单信息是否相符
        $pass = false;
        if (isset($transaction['events']) && is_array($transaction['events'])) {
            foreach ($transaction['events'] as $event) {
                if ($event['type'] === '0x1::fungible_asset::Deposit') {
                    if (
                        $event['data']['owner'] === $order->channel_id &&
                        bccomp($event['data']['amount'], bcmul((string)$order->amount, '100000000', 0), 0) === 0
                    ) {
                        //收款信息相符
                        $pass = true;
                        break;
                    }
                }
            }
        }
        if (!$pass) {
            Log::channel('important')
                ->warning("订单数据异常 未找到收款信息 order_id=$order_id hash=$hash\n" . json_enc($transaction));
            throw new BusinessError('订单数据异常 未找到收款信息');
        }

        //开始处理订单和用户数据
        Db::beginTransaction();
        try {
            //修改订单为已完成
            Order::query()
                ->where('id', '=', $order->id)
                ->where('status', '=', 0)
                ->update([
                    'payment_at' => Carbon::createFromTimestampMs(
                        bcdiv($transaction['timestamp'], '1000', 0)
                    )->toISOString(),
                    'status' => 1,
                    'channel_order_no' => $hash,
                    'channel_order_info' => json_enc($transaction),
                ]);

            $extra = json_decode($order->extra, true);

            //增加用户VIP天数
            G(UserService::class)->addExpires($order->user_id, $extra['days']);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }
}