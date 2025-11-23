<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\User;
use app\model\UserConnect;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use support\Db;
use support\exception\BusinessError;
use support\Redis;

class UserService
{
    /**
     * 获取用户列表
     * @param array $params
     * @return array
     */
    public function getList(array $params): array
    {
        $query = User::query()
            ->leftJoin('user_connect AS user_connect_luffa', function (JoinClause $join) {
                $join->on('user_connect_luffa.user_id', '=', 'user.id')
                    ->where('user_connect_luffa.platform', '=', 'luffa');
            })
            ->leftJoin('user_connect AS user_connect_email', function (JoinClause $join) {
                $join->on('user_connect_email.user_id', '=', 'user.id')
                    ->where('user_connect_email.platform', '=', 'email');
            });

        if (!empty($params['register_date_start'])) {
            $query->where(
                'user.created_at',
                '>=',
                Carbon::parse($params['register_date_start'])->toISOString(),
            );
        }
        if (!empty($params['register_date_end'])) {
            $query->where(
                'user.created_at',
                '<',
                Carbon::parse($params['register_date_end'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        if (!empty($params['luffa_id'])) {
            $query->where('user_connect_luffa.account', '=', $params['luffa_id']);
        }
        if (!empty($params['email'])) {
            $query->where('user_connect_email.account', '=', $params['email']);
        }

        $count = $query->count();

        $list = $query
            ->orderBy('user.id', 'desc')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get([
                'user.id',
                'user.nickname',
                'user.avatar',
                'user.status',
                'user.expire_time',
                'user.created_at',
                'user.reg_source',
            ])
            ->toArray();

        //读取用户的其他登录属性
        if (!empty($list)) {
            $user_ids = array_column($list, 'id');
            $connects = UserConnect::query()
                ->whereIn('user_id', $user_ids)
                ->get([
                    'user_id',
                    'platform',
                    'account',
                ])
                ->toArray();

            foreach ($list as $k => $row) {
                //读取用户的各个子账号数据
                $list[$k]['luffa'] = array_find($connects, fn(array $connect) => $connect['platform'] === 'luffa' && $connect['user_id'] === $row['id']);
                $list[$k]['email'] = array_find($connects, fn(array $connect) => $connect['platform'] === 'email' && $connect['user_id'] === $row['id']);
            }
        }

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 获取用户详情
     * @param int $id
     * @return array
     */
    public function getDetails(int $id): array
    {
        $user = User::query()
            ->where('id', '=', $id)
            ->first([
                'id',
                'username',
                'status',
                'expire_time',
                'note',
                'agent1_id',
                'agent2_id',
            ]);

        if (!$user) {
            throw new BusinessError('未找到数据');
        }

        $user = $user->toArray();
        $user['password'] = '';

        return $user;
    }

    /**
     * 保存用户
     * @param array $data
     * @return void
     */
    public function save(array $data): void
    {
        if (!empty($data['id'])) {
            $user = User::query()
                ->where('id', '=', $data['id'])
                ->first();
            if (!$user) {
                throw new BusinessError('未找到数据');
            }
        } else {
            $user = new User();
            $user->username = $data['username'];
            $user->agent1_id = 0;
            $user->agent2_id = 0;
        }

        if (!empty($data['password'])) {
            $user->password = md5($data['password']);
        }
        $user->status = $data['status'];
        $user->expire_time = $data['expire_time'];
        $user->note = $data['note'];
        $user->save();
    }

    /**
     * 增加用户的VIP有效期
     * @param int $user_id
     * @param int $days
     * @return User
     */
    public function addExpireTime(int $user_id, int $days): User
    {
        User::query()
            ->where('id', '=', $user_id)
            ->update([
                'expire_time' => User::raw("expire_time + interval '$days days'")
            ]);

        $user = get_user($user_id, false);
        if (!$user) {
            throw new BusinessError('用户不存在');
        }

        return $user;
    }

    /**
     * 设置用户的VIP有效期
     * @param int $user_id
     * @param string $expire_time
     * @return User
     */
    public function setExpireTime(int $user_id, string $expire_time): User
    {
        User::query()
            ->where('id', '=', $user_id)
            ->update([
                'expire_time' => Carbon::parse($expire_time),
            ]);

        $user = get_user($user_id, false);
        if (!$user) {
            throw new BusinessError('用户不存在');
        }

        return $user;
    }

    /**
     * 设置用户的状态
     * @param int $user_id
     * @param int $status
     * @return void
     */
    public function setStatus(int $user_id, int $status): void
    {
        User::query()
            ->where('id', '=', $user_id)
            ->update([
                'status' => $status,
            ]);
        $this->clearUserCache($user_id);
    }

    /**
     * 清理用户缓存
     * @param int[] $user_ids
     * @return void
     */
    public function clearUserCache(int ...$user_ids): void
    {
        if (empty($user_ids)) return;
        Redis::del(...array_map(fn($user_id) => CACHE_USER_KEY . $user_id, $user_ids));
    }
}