<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Agent;
use app\model\LuffaUser;
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
     * 用户登录
     * @return array{
     *     token: string,
     *     user: User
     * }
     */
    public function login(array $params): array
    {
        $user = User::query()->where('username', '=', $params['username'])->first();
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
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        if (is_string($user->expire_time)) {
            $expireTime = Carbon::parse($user->expire_time);
        } else {
            $expireTime = $user->expire_time;
        }
        $user->is_expired = $expireTime->unix() <= time();

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Luffa授权登录
     * @param array $params
     * @return array
     */
    public function luffaLogin(string $network, array $params): array
    {
        //查询用户
        $luffaUser = LuffaUser::query()
            ->where('network', '=', $network)
            ->where('uid', '=', $params['uid'])
            ->first();
        if ($luffaUser) {
            $user = get_user($luffaUser->user_id);
        } else {
            //创建用户
            $vip_seconds = config('app.new_user_expires');

            $user = new User();
            $user->username = implode(':', ['luffa', $network, $params['uid']]);
            $user->password = '';
            $user->expire_time = Carbon::now()->addSeconds($vip_seconds)->toISOString();
            $user->email = implode(':', ['luffa', $network, $params['uid']]);

            $luffaUser = new LuffaUser();
            $luffaUser->network = $network;
            $luffaUser->uid = $params['uid'];
            $luffaUser->nickname = $params['nickname'] ?? null;
            $luffaUser->avatar = $params['avatar'] ?? null;
            $luffaUser->cid = $params['cid'] ?? null;
            $luffaUser->address = $params['address'] ?? null;

            Db::beginTransaction();
            try {
                $user->save();
                $luffaUser->user_id = $user->id;
                $luffaUser->save();

                Db::commit();
            } catch (Throwable $e) {
                Db::rollBack();
                throw $e;
            }
        }

        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        if (is_string($user->expire_time)) {
            $expireTime = Carbon::parse($user->expire_time);
        } else {
            $expireTime = $user->expire_time;
        }
        $user->is_expired = $expireTime->unix() <= time();

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

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

        $agent1_id = 0;
        $agent2_id = 0;

        if (!empty($params['invite_code'])) {
            //再检查邀请码对应的代理
            $agent = Agent::query()
                ->where('code', '=', $params['invite_code'])
                ->first();
            if (!$agent || $agent->status !== 1) {
                throw new BusinessError('无效的邀请码');
            }

            //根据代理确定用户归属
            if (!empty($agent->parent_id)) {
                $agent1_id = $agent->parent_id;
                $agent2_id = $agent->id;
            } else {
                $agent1_id = $agent->id;
            }
        }

        //创建用户
        $userId = User::insertGetId([
            'username' => $params['username'],
            'password' => md5($params['password']),
            'status' => 1,
            'expire_time' => Carbon::now()->addMinutes(5)->toISOString(),
            'agent1_id' => $agent1_id,
            'agent2_id' => $agent2_id,
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
     * 增加VIP天数
     * @return void
     */
    public function addExpires(int $user_id, int $days): void
    {
        $user = User::query()
            ->where('id', '=', $user_id)
            ->first(['expire_time']);
        if (!$user) {
            throw new BusinessError('用户不存在');
        }

        /** @var Carbon $expire_time */
        $expire_time = $user->expire_time;

        if ($expire_time->timestamp < time()) {
            $expire_time = Carbon::now()->addDays($days);
        } else {
            $expire_time = $expire_time->addDays($days);
        }

        User::query()->where('id', '=', $user_id)->update(['expire_time' => $expire_time->toISOString()]);
        Redis::del(CACHE_USER_KEY . $user_id);
    }
}