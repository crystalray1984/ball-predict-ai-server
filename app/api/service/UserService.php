<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;
use app\model\User;
use Carbon\Carbon;
use support\exception\BusinessError;
use support\Redis;
use support\Token;

/**
 * 用户业务逻辑
 */
class UserService
{
    /**
     * 给用户加VIP天数
     * @param int $user_id
     * @param int $days
     * @return void
     */
    public function addExpires(int $user_id, int $days): void
    {
        /** @var User $user */
        $user = User::query()
            ->where('id', '=', $user_id)
            ->first(['expire_time']);
        if (!$user) {
            throw new BusinessError('用户不存在');
        }

        if ($user->expire_time->unix() < time()) {
            $expire_time = Carbon::now()->addDays($days);
        } else {
            $expire_time = $user->expire_time->addDays($days);
        }

        User::query()
            ->where('id', '=', $user_id)
            ->update([
                'expire_time' => $expire_time->toISOString()
            ]);

        Redis::del(CACHE_USER_KEY . $user_id);
    }

    /**
     * 获取VIP购买记录
     * @param int $user_id
     * @param int $page
     * @param int $page_size
     * @return array
     */
    public function getVipRecords(int $user_id, int $page = DEFAULT_PAGE, int $page_size = DEFAULT_PAGE_SIZE): array
    {
        $query = Order::query()
            ->where('user_id', '=', $user_id)
            ->where('status', '=', 'paid')
            ->where('type', '=', 'vip');

        $total = $query->count();

        $list = $query
            ->orderBy('paid_at', 'desc')
            ->forPage($page, $page_size)
            ->get([
                'id',
                'type',
                'amount',
                'channel_type',
                'paid_at',
                'extra',
                'currency',
            ])
            ->toArray();

        foreach ($list as $k => $row) {
            $list[$k]['channel'] = $row['channel_type'];
            $list[$k]['payment_at'] = $row['paid_at'];
            $list[$k]['amount'] = strval(floatval($row['amount']));
            $list[$k]['extra'] = json_decode($row['extra'], true);
            unset($list[$k]['channel_type'], $list[$k]['paid_at']);
        }

        return [
            'total' => $total,
            'list' => $list,
        ];
    }
}