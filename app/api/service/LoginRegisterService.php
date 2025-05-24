<?php declare(strict_types=1);

namespace app\api\service;

use app\model\User;
use app\model\UserConnect;
use Carbon\Carbon;
use support\Db;
use support\exception\BusinessError;
use Throwable;

/**
 * 用户注册/登录服务
 */
class LoginRegisterService
{
    /**
     * Luffa小程序登录
     * @param array{
     *     uid: string
     * } $params
     * @return User
     */
    public function luffaLogin(string $network, array $params): User
    {
        //首先尝试通过luffa小程序看看用户是否存在
        /** @var UserConnect $connect */
        $connect = UserConnect::query()
            ->where('platform', '=', 'luffa')
            ->where('platform_id', '=', $network)
            ->where('account', '=', $params['uid'])
            ->first(['user_id']);
        if ($connect) {
            //已经找到用户就直接返回
            $user = get_user($connect->user_id);
            if (!$user) {
                throw new BusinessError('用户不存在');
            }
            if (!$user->status) {
                throw new BusinessError('用户已被禁用');
            }
            return $user;
        }

        //尝试创建用户
        $connect = new UserConnect();
        $connect->platform = 'luffa';
        $connect->platform_id = $network;
        $connect->account = $params['uid'];
        $connect->extra = json_enc($params);

        Db::beginTransaction();
        try {
            //创建用户
            $user = $this->createUser([
                'reg_source' => 'luffa',
            ]);

            //写入用户连接表里的用户id并保存
            $connect->user_id = $user->id;
            $connect->save();
            Db::commit();

            return $user;
        } catch (Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    /**
     * 邮箱+密码登录
     * @param array $params
     * @return User
     */
    public function emailPasswordLogin(array $params): User
    {
        /** @var UserConnect $connect */
        $connect = UserConnect::query()
            ->where('platform', '=', 'email')
            ->where('account', '=', $params['username'])
            ->first();

        if (!$connect) {
            throw new BusinessError('用户不存在');
        }

        //检查密码
        if ($connect->password !== md5($params['password'])) {
            throw new BusinessError('密码错误');
        }

        $user = get_user($connect->user_id);

        if (!$user) {
            throw new BusinessError('用户不存在');
        }

        if (!$user->status) {
            throw new BusinessError('用户已被禁用');
        }

        return $user;
    }

    /**
     * 创建用户
     * @param array $userInfo 用户信息字段
     * @return User
     */
    public function createUser(array $userInfo = []): User
    {
        if (!isset($userInfo['expire_time'])) {
            $settings = get_settings(['new_user_expire_hours']);
            $userInfo['expire_time'] = Carbon::now()->addHours($settings['new_user_expire_hours']);
        }

        return User::create($userInfo);
    }
}