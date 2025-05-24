<?php declare(strict_types=1);

namespace app\api\service;

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
     * 用户注册
     * @param array $params
     * @return array
     */
    public function register(array $params): array
    {
        //先检查用户名是否存在
        $usernameExists = User::query()
            ->where('username', '=', $params['username'])
            ->exists();

        if ($usernameExists) {
            throw new BusinessError('账号已被使用');
        }

        //创建用户
        $userId = User::insertGetId([
            'username' => $params['username'],
            'password' => md5($params['password']),
            'status' => 1,
            'expire_time' => Carbon::now()->addMinutes(5)->toISOString(),
        ]);

        $user = User::query()->where('id', '=', $userId)->first();

        //生成token
        $token = Token::create(['id' => $userId, 'type' => 'user']);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

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
}