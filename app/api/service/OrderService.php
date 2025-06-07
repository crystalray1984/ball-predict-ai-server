<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use League\Uri\Uri;
use Plisio\ClientAPI;
use support\Db;
use support\Endless;
use support\exception\BusinessError;
use support\Log;
use support\Plisio;
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
            ->where('id', '=', $order_id)
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

    /**
     * 创建Plisio订单
     * @param int $user_id
     * @param string $type
     * @return array
     */
    public function createPlisioOrder(
        int     $user_id,
        string  $type,
        ?string $client_type = null,
        ?string $redirect_url = null,
    ): array
    {
        //读取配置
        $config = config("payment.plisio");

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
        $order->channel_type = 'plisio';
        $order->extra = json_enc($order_info);

        Db::beginTransaction();
        try {
            //第一次保存订单
            $order->save();

            //订单保存后生成订单编号
            $order->order_number = $order->order_date . str_pad((string)$order->id, 6, '0', STR_PAD_LEFT);

            //创建支付完成后的跳转地址
            $redirect_urls = $this->createRedirectUrl($order->id, $client_type, $redirect_url);

            $plisio = new ClientAPI(config('payment.plisio.secret'));
            $channel_order_info = $plisio->createTransaction([
                'order_name' => '188ZQ VIP',
                'order_number' => $order->order_number,
                'source_currency' => 'USD',
                'source_amount' => $order_info['price'],
                'allowed_psys_cids' => 'USDT,USDT_TRX',
                'success_callback_url' => config('app.server_url') . '/api/order/callback/plisio',
                'success_invoice_url' => $redirect_urls['success'],
                'fail_invoice_url' => $redirect_urls['fail'],
            ]);

            //写入订单号到订单中
            $order->channel_order_no = $channel_order_info['txn_id'];
            $order->save();

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        //返回订单数据
        return [
            'order_id' => $order->id,
            'payment_data' => [
                'invoice_url' => $channel_order_info['invoice_url'],
                'success_invoice_url' => $redirect_urls['success'],
                'fail_invoice_url' => $redirect_urls['fail'],
            ],
        ];
    }

    /**
     * 创建回跳的页面地址
     * @param int $order_id
     * @param string|null $client_type
     * @param string|null $redirect_url
     * @return string[]
     */
    public function createRedirectUrl(int $order_id, ?string $client_type, ?string $redirect_url): array
    {
        if (empty($redirect_url)) {
            //如果没有设置回跳地址，那么按照标准的地址来回跳
            $redirect_url = config('app.server_url') . '/api/order/finish';
        }

        //构建完整的地址
        $uri = Uri::new($redirect_url);

        //添加通用的参数
        $commonQuery = [
            'order_id' => $order_id,
        ];
        if (!empty($client_type)) {
            $commonQuery['client_type'] = $client_type;
        }
        $uri = $uri->withQuery(http_build_query($commonQuery));

        //添加成功与失败的参数
        $success = $uri->withQuery('success=1');
        $fail = $uri->withQuery('success=0');

        return [
            'url' => $uri->toString(),
            'success' => $success->toString(),
            'fail' => $fail->toString(),
        ];
    }

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
        if ($order->status === 'wait_pay') {
            switch ($order->channel_type) {
                case 'plisio':
                    $this->checkPlisioOrder($order);
                    break;
                default:
                    break;
            }
        }

        //返回订单数据
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $order->user_id,
            'type' => $order->type,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => $order->status,
            'extra' => json_decode($order->extra, true),
            'channel_type' => $order->channel_type,
            'channel_order_no' => $order->channel_order_no,
            'paid_at' => $order->paid_at,
            'created_at' => $order->created_at,
        ];
    }

    /**
     * 从plisio查询订单的状态
     * @param Order $order
     * @return void
     */
    public function checkPlisioOrder(Order $order): void
    {
        //调用通道接口
        try {
            $transaction = G(Plisio::class)->getTransaction($order->channel_order_no);
        } catch (Throwable) {
            return;
        }

        if ($transaction['status'] !== 'success') {
            return;
        }

        //比对订单号
        if ($transaction['data']['id'] !== $order->channel_order_no) {
            return;
        }

        //比对订单状态
        if ($transaction['data']['status'] !== 'completed') {
            return;
        }

        //比对订单类型
        if ($transaction['data']['type'] !== 'invoice') {
            return;
        }

        //比对订单金额
        if (bccomp($transaction['data']['amount'], $order->amount, 2) === 0) {
            return;
        }

        //更新订单数据
        Db::beginTransaction();
        try {
            $order->channel_order_info = json_enc($transaction['data']);
            $order->status = 'paid';
            $order->paid_at = Carbon::createFromTimestamp($transaction['data']['finished_at_utc']);
            $order->save();

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