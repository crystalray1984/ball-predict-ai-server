<?php declare(strict_types=1);

namespace app\api\service;

use app\model\User;
use support\exception\BusinessError;
use support\Token;

/**
 * 用户业务逻辑
 */
class UserService
{
    /**
     * 用户登录
     * @param string $username
     * @param string $password
     * @return array{
     *     token: string,
     *     user: User
     * }
     */
    public function login(string $username, string $password): array
    {
        $user = User::query()->where('username', '=', $username)->first();
        if (!$user) {
            throw new BusinessError('账号不存在');
        }

        if ($user->password !== md5($password)) {
            throw new BusinessError('密码错误');
        }

        if ($user->expire_time->timestamp < time()) {
            throw new BusinessError('账号已到期');
        }

        if ($user->status !== 1) {
            throw new BusinessError('账号已被禁用');
        }

        //生成token
        $token = Token::create(['id' => $user->id]);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }
}