<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;
use app\model\User;
use app\model\UserCommission;
use Carbon\Carbon;
use support\Db;
use support\exception\BusinessError;
use support\Luffa;
use support\payment\Engine;
use Throwable;

/**
 * 订单与支付业务
 */
class OrderService
{
    /**
     * 查询订单
     * @param int $order_id
     * @param int|null $user_id
     * @return array
     */
    public function queryOrder(int $order_id, ?int $user_id = null): array
    {
        //首先检查订单是否存在
        /** @var Order $order */
        $order = Order::query()
            ->where('id', '=', $order_id)
            ->when(!empty($user_id), fn($query) => $query->where('user_id', '=', $user_id))
            ->first();

        if (!$order) {
            throw new BusinessError('订单不存在');
        }

        //如果订单没支付，就根据不同的通道类型，尝试去检查订单的状态
        if ($order->status === 'wait_pay' && !empty($order->channel_order_no)) {
            try {
                $this->completeOrder($order);
            } catch (Throwable $e) {
                if ($e instanceof BusinessError) {
                    return [
                        'id' => $order->id,
                        'status' => 'wait_pay',
                    ];
                }
                throw $e;
            }
        }

        //返回订单数据
        return [
            'id' => $order->id,
            'status' => $order->status,
        ];
    }

    /**
     * 创建VIP订单
     * @param int $user_id
     * @param string $channel
     * @param string $vip_type
     * @return array
     */
    public function createVipOrder(int $user_id, string $channel, string $vip_type): array
    {
        /** @var Engine $engine */
        $engine = G(Engine::ENGINES[$channel]);
        $vip_config = config("vip.$vip_type");
        $price_config = $vip_config['price'][$channel];

        //VIP信息
        $vip_info = [
            'type' => $vip_type,
            'days' => $vip_config['days'],
            'price' => $price_config['price'],
            'currency' => $price_config['currency'],
        ];

        //创建业务订单
        $now = Carbon::now();

        $order = new Order();
        $order->order_date = (int)$now->format('Ymd');
        $order->user_id = $user_id;
        $order->type = 'vip';
        $order->amount = $price_config['price'];
        $order->currency = $price_config['currency'];
        $order->channel_type = $channel;
        $order->extra = json_enc($vip_info);

        Db::beginTransaction();
        try {
            $order->save();

            //生成订单编号
            $order->order_number = $order->order_date . str_pad((string)$order->id, 6, '0', STR_PAD_LEFT);

            //生成三方支付订单
            $result = $engine->create($order);

            //保存订单数据
            $order->save();

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        //返回给前端的支付数据
        return [
            'order_id' => $order->id,
            'payment_type' => $engine->getPaymentType(),
            'payment_data' => $result,
        ];
    }

    /**
     * 完成订单
     * @param Order $order
     * @return void
     */
    public function completeOrder(Order $order): void
    {
        /** @var Engine $engine */
        $engine = G(Engine::ENGINES[$order->channel_type]);

        //读取订单数据
        $transaction = $engine->check($order);
        if (empty($transaction)) {
            throw new BusinessError('订单未支付完成');
        }

        if (!$engine->complete($order, $transaction)) {
            throw new BusinessError('订单未支付完成');
        }

        //更新订单
        $order->status = 'paid';
        $order->paid_at = Carbon::now();

        Db::beginTransaction();
        try {
            $order->save();
            $extra = json_decode($order->extra, true);

            //增加用户VIP天数
            G(UserService::class)->addExpires($order->user_id, $extra['days']);


            //发放佣金
            if ($order->currency === 'USDT') {
                //查询用户是否有邀请人
                $invite_user_id = User::query()->where('id', '=', $order->user_id)->value('invite_user_id');
                if (!empty($invite_user_id)) {
                    //计算佣金
                    $commissionConfig = config('commission');
                    $commission = bcmul((string)$order->amount, (string)$commissionConfig['ratio'], 2);
                    if (bccomp($commission, '0', 2) > 0) {
                        //插入佣金记录
                        UserCommission::insert([
                            'user_id' => $invite_user_id,
                            'order_id' => $order->id,
                            'commission' => $commission,
                        ]);
                    }
                }
            }

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        $channel_type_name = match ($order->channel_type) {
            'endless' => 'EDS',
            'tron' => '波场',
            'ethereum' => '以太坊',
            default => $order->channel_type,
        };

        $vip_type = match ($extra['type']) {
            'day' => '日卡',
            'week' => '周卡',
            'month' => '月卡',
            default => $extra['type'],
        };

        $amount = floatval($order->amount);

        //发送订单通知
        Luffa::sendNotification(<<<EOF
**用户购买VIP通知**
用户id {$order->user_id}
类型 $vip_type
购买方式 $channel_type_name
购买价格 $amount {$order->currency}
EOF
        );
    }
}