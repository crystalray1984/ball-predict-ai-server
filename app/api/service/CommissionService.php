<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;
use app\model\User;
use app\model\UserCommission;
use app\model\UserCommissionRecord;
use app\model\UserConnect;
use app\model\UserWithdrawal;
use support\Db;
use support\exception\BusinessError;
use Throwable;

class CommissionService
{
    /**
     * 获取用户的在途佣金
     * @param int $user_id 用户id
     * @return string
     */
    public function getUserIncomingCommission(int $user_id): string
    {
        $row = UserCommission::query()
            ->where('user_id', '=', $user_id)
            ->whereNull('settled_at')
            ->selectRaw('SUM(commission) AS commission')
            ->first();
        if (!$row || empty($row->commission)) {
            return '0';
        }
        return $row->commission;
    }

    /**
     * 获取用户的佣金记录
     * @param int $user_id 用户id
     * @param array $params
     * @return array
     */
    public function getUserCommissionList(int $user_id, array $params): array
    {
        $query = UserCommission::query()
            ->where('user_id', '=', $user_id);

        if (isset($params['settled']) && $params['settled'] !== -1) {
            //指定查询某个状态的记录
            if ($params['settled']) {
                $query->whereNotNull('settled_at');
            } else {
                $query->whereNull('settled_at');
            }
        }

        //读取列表总数
        $count = $query->count();

        $list = $query
            ->orderBy('created_at', 'DESC')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get()
            ->toArray();

        //处理列表信息
        if (!empty($list)) {
            //通过列表查询订单信息
            $order_ids = array_column($list, 'order_id');
            $orders = Order::query()
                ->whereIn('id', $order_ids)
                ->get([
                    'id',
                    'user_id',
                    'extra',
                ])
                ->toArray();

            //解析订单信息
            $orders = array_map(fn(array $row) => [
                ...$row,
                'extra' => json_decode($row['extra'], true),
            ], $orders);
            $orders = array_column($orders, null, 'id');

            //解析订单用户信息
            $order_user_ids = array_values(array_unique(array_column($orders, 'user_id')));
            $order_user_connects = UserConnect::query()
                ->whereIn('user_id', $order_user_ids)
                ->orderBy('id', 'DESC')
                ->get(['id', 'user_id', 'platform', 'platform_id', 'account'])
                ->toArray();
            //组合订单用户数据
            $order_users = [];
            foreach ($order_user_ids as $order_user_id) {
                //如果指定了客户端类型，那么尝试获取这个类型的用户信息
                if (!empty($params['client_type'])) {
                    $connect_row = array_find(
                        $order_user_connects,
                        fn(array $row) => $row['user_id'] === $order_user_id && $row['platform'] === $params['client_type']
                    );

                    //找到了对应的用户信息，就把这个信息写入
                    if ($connect_row) {
                        $order_users[$order_user_id] = $connect_row;
                        continue;
                    }
                }

                //按照最后绑定的方式返回最后的用户信息
                $order_users[$order_user_id] = array_find(
                    $order_user_connects,
                    fn(array $row) => $row['user_id'] === $order_user_id
                );
            }

            //插入订单数据
            $list = array_map(function (array $row) use ($orders, $order_users) {
                //订单类型数据
                $order = $orders[$row['order_id']];
                $row['vip_type'] = $order['extra']['type'];

                //订单用户信息
                $row['user'] = $orders[$order['user_id']];

                return $row;
            }, $list);
        }

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 获取用户的佣金变更记录
     * @param int $user_id
     * @param array $params
     * @return array
     */
    public function getChangeList(int $user_id, array $params): array
    {
        $query = UserCommissionRecord::query()
            ->where('user_id', '=', $user_id);
        if (!empty($params['type'])) {
            $query->whereIn('type', $params['type']);
        }

        $count = $query->count();
        $list = $query
            ->orderBy('created_at', 'DESC')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get()
            ->toArray();

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 佣金提现
     * @param int $user_id
     * @param array $data
     * @return void
     */
    public function withdrawal(int $user_id, array $data): void
    {
        $config = config('commission');

        //检查提现通道
        if (!isset($config['channels'][$data['channel_type']])) {
            throw new BusinessError('无效的提现方式');
        }

        //检查最小提现金额
        $channel = $config['channels'][$data['channel_type']];
        if ($data['amount'] < $channel['min_amount']) {
            throw new BusinessError("提现金额最少为" . $channel['min_amount']);
        }

        //检查用户的可提现金额
        /** @var User $user */
        $user = User::query()
            ->where('id', '=', $user_id)
            ->first(['id', 'commission']);

        if (bccomp((string)$data['amount'], $user->commission, 2) > 0) {
            throw new BusinessError('可提现余额不足');
        }

        Db::beginTransaction();
        try {
            //先扣减余额
            $updated = User::query()
                ->where('id', '=', $user_id)
                ->where('commission', '>=', $data['amount'])
                ->update([
                    'commission' => User::raw('commission - ' . $data['amount']),
                ]);

            if ($updated) {
                throw new BusinessError('可提现余额不足');
            }

            //查询一下用户现在的佣金余额，计入用户余额变更记录
            /** @var User $user */
            $user = User::query()
                ->where('id', '=', $user_id)
                ->first(['id', 'commission']);

            //插入佣金变更记录
            UserCommissionRecord::insert([
                'user_id' => $user_id,
                'type' => 'withdrawal',
                'amount' => 0 - $data['amount'],
                'amount_after' => $user->commission,
            ]);

            //插入提现记录
            UserWithdrawal::insert([
                'user_id' => $user_id,
                'amount' => $data['amount'],
                'channel_type' => $data['channel_type'],
                'withdrawal_account' => $data['withdrawal_account'],
            ]);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }
}