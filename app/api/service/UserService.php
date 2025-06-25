<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Order;
use app\model\User;
use Carbon\Carbon;
use support\Db;
use support\exception\BusinessError;
use support\Redis;
use support\Token;
use Throwable;

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

    /**
     * 绑定用户的邀请码
     * @param int $user_id
     * @param string $inviter_code
     * @return void
     */
    public function bindInviter(int $user_id, string $inviter_code): void
    {
        //先查询邀请码对应的用户
        /** @var User $inviter */
        $inviter = User::query()
            ->where('code', '=', $inviter_code)
            ->first(['id']);
        if (!$inviter) {
            throw new BusinessError('无效的邀请码');
        }

        if ($inviter->id === $user_id) {
            throw new BusinessError('不可绑定自己');
        }

        //然后查询当前用户是否已经存在邀请关系
        /** @var User $user */
        $user = User::query()
            ->where('id', '=', $user_id)
            ->first(['id', 'invite_user_id']);
        if (!empty($user->invite_user_id)) {
            throw new BusinessError('已经存在邀请关系，不可重复绑定');
        }

        Db::beginTransaction();
        try {
            //尝试修改当前用户的邀请关系
            $updated = User::query()
                ->where('id', '=', $user_id)
                ->where('invite_user_id', '=', 0)
                ->update([
                    'invite_user_id' => $inviter->id,
                    'invited_at' => User::raw('CURRENT_TIMESTAMP'),
                ]);
            if (!$updated) {
                throw new BusinessError('已经存在邀请关系，不可重复绑定');
            }

            //绑定成功给用户加VIP时长
            $this->addExpires($user_id, 1);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        //清空用户的缓存
        Redis::del(CACHE_USER_KEY . $user_id);
    }
}