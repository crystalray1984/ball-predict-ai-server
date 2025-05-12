<?php declare(strict_types=1);

namespace app\api\service;

use app\model\Agent;
use app\model\LuffaUser;
use app\model\User;
use Carbon\Carbon;
use support\Db;
use support\exception\BusinessError;
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
    public function luffaLogin(array $params): array
    {
        //查询用户
        $luffaUser = LuffaUser::query()->where('uid', '=', $params['uid'])->first();
        if ($luffaUser) {
            $user = get_user($luffaUser->user_id);
        } else {
            //创建用户
            $user = new User();
            $user->username = 'luffa:' . $params['uid'];
            $user->password = '';
            $user->expire_time = Carbon::now()->addDays(365)->toISOString();
            $user->email = 'luffa:' . $params['uid'];

            $luffaUser = new LuffaUser();
            $luffaUser->uid = $params['uid'];
            $luffaUser->nickname = $params['nickname'] ?? null;
            $luffaUser->avatar = $params['avatar'] ?? null;
            $luffaUser->cid = $params['cid'] ?? null;
            $luffaUser->avatar_frame = $params['avatar_frame'] ?? null;
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
}