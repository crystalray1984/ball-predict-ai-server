<?php declare(strict_types=1);

namespace app\api\service;

use app\model\User;
use app\model\UserConnect;
use Carbon\Carbon;
use Sqids\Sqids;
use support\Db;
use support\exception\BusinessError;
use support\Redis;
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

        //处理用户的头像
        if (isset($params['avatar'])) {
        }

        if ($connect) {
            //更新用户信息

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
     * 通过邮箱注册
     * @param array $params
     * @return User
     */
    public function emailRegister(array $params): User
    {
        //检查邮箱验证码
        $code = Redis::get('email_code:' . $params['username']);
        if (empty($code)) {
            throw new BusinessError('验证码错误');
        }

        $connect = UserConnect::query()
            ->where('platform', '=', 'email')
            ->where('account', '=', $params['username'])
            ->first(['id']);

        if ($connect) {
            throw new BusinessError('此邮箱已被使用');
        }

        $connect = new UserConnect();
        $connect->platform = 'email';
        $connect->account = $params['username'];
        $connect->password = md5($params['password']);

        $nickname = substr($params['username'], 0, strpos($params['username'], '@'));

        Db::beginTransaction();
        try {
            //创建用户
            $user = $this->createUser([
                'reg_source' => 'email',
                'nickname' => $nickname,
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

        Db::beginTransaction();
        try {
            $user = User::create($userInfo);

            //拆解用户id
            $codeStr = str_pad((string)$user->id, 6, '0', STR_PAD_LEFT);
            $codeNumbers = array_map(fn(string $v) => (int)$v, str_split($codeStr));

            //生成用户邀请码
            $user->code = G(Sqids::class)->encode($codeNumbers);
            $user->save();

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        return $user;
    }
}