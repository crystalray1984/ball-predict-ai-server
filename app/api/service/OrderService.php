<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;
use Carbon\Carbon;
use support\Db;
use support\Endless;
use support\exception\BusinessError;
use support\Log;
use Throwable;

/**
 * 订单与支付业务
 */
class OrderService
{
    /**
     * 创建Luffa订单
     * @return array
     */
    public function createLuffaOrder(int $user_id, string $network, string $type): array
    {
        //读取配置
        $config = config("payment.$network");

        //订单信息
        $order_info = $config['config'][$type];
        $order_info['type'] = $type;

        //创建订单信息
        $now = Carbon::now();

        $order = new Order();
        $order->order_date = (int)$now->format('Ymd');
        $order->user_id = $user_id;
        $order->type = 'vip';
        $order->amount = $order_info['price'];
        $order->currency = $order_info['currency'];
        $order->channel_type = $network;
        $order->channel_id = $config['income_address'];
        $order->extra = json_enc($order_info);
        $order->save();

        //订单保存后生成订单编号
        $order->order_number = $order->order_date . str_pad((string)$order->id, 6, '0', STR_PAD_LEFT);
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
        /** @var Order $order */
        $order = Order::query()
            ->where('id', $order_id)
            ->first();

        if (!$order) {
            throw new BusinessError('订单不存在');
        }
        if ($order->status !== 'wait_pay') {
            //订单已完成
            return;
        }

        //判断订单类型必须是endless或者eds
        if ($order->channel_type !== 'endless' && $order->channel_type !== 'eds') {
            throw new BusinessError('订单类型错误');
        }

        //调用接口检查订单是否完成
        $transaction = Endless::create($order->channel_type)->getTransaction($hash);
        if (empty($transaction)) {
            //订单未支付
            throw new BusinessError('订单未支付');
        }

        //检查订单信息
        if (($transaction['hash'] ?? '') !== $hash) {
            Log::channel('endless')
                ->warning("[Luffa订单完成] 订单数据异常 hash不匹配 order_id=$order_id hash=$hash\n" . json_enc($transaction));
            throw new BusinessError('订单数据异常');
        }

        if (($transaction['success'] ?? false) !== true) {
            Log::channel('endless')
                ->warning("[Luffa订单完成] 订单数据异常 success不正确 order_id=$order_id hash=$hash\n" . json_enc($transaction));
            throw new BusinessError('订单数据异常 支付未完成');
        }

        //寻找交易信息中的收款人与订单信息是否相符
        $pass = false;
        if (isset($transaction['events']) && is_array($transaction['events'])) {
            foreach ($transaction['events'] as $event) {
                if ($event['type'] === '0x1::fungible_asset::Deposit') {
                    if (
                        $event['data']['owner'] === $order->channel_id &&
                        bccomp($event['data']['amount'], bcmul($order->amount, '100000000', 0), 0) === 0
                    ) {
                        //收款信息相符
                        $pass = true;
                        break;
                    }
                }
            }
        }
        if (!$pass) {
            Log::channel('endless')
                ->warning("[Luffa订单完成]订单数据异常 未找到收款信息 order_id=$order_id hash=$hash\n" . json_enc($transaction));
            throw new BusinessError('订单数据异常 未找到收款信息');
        }

        //开始处理订单和用户数据
        Db::beginTransaction();
        try {
            //修改订单为已完成
            $updated = Order::query()
                ->where('id', '=', $order->id)
                ->where('status', '=', 'wait_pay')
                ->update([
                    'paid_at' => Carbon::createFromTimestampMs(
                        bcdiv($transaction['timestamp'], '1000', 0)
                    )->toISOString(),
                    'status' => 'paid',
                    'channel_order_no' => $hash,
                    'channel_order_info' => json_enc($transaction),
                ]);

            if (!$updated) {
                //订单状态不对
                Db::rollBack();
                return;
            }

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