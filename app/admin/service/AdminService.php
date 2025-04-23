<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Admin;
use support\exception\BusinessError;
use support\Token;

class AdminService
{
    /**
     * 管理端用户登录
     * @param array $params
     * @return array
     */
    public function login(array $params): array
    {
        $user = Admin::query()
            ->where('username', '=', $params['username'])
            ->first();

        if (!$user) {
            throw new BusinessError('账号不存在');
        }

        if ($user->password !== md5($params['password'])) {
            throw new BusinessError('密码错误');
        }

        if ($user->status !== 1) {
            throw new BusinessError('账号已被禁用');
        }

        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'admin']);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }
}