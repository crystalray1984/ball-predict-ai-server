<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\User;
use support\exception\BusinessError;

class UserService
{
    /**
     * 获取用户列表
     * @param array $params
     * @return array
     */
    public function getList(array $params): array
    {
        $query = User::query();
        if (isset($params['agent_id'])) {
            if ($params['agent_id'] > 0) {
                $query->where(function ($where) use ($params) {
                    $where->where('agent1_id', '=', $params['agent_id'])
                        ->orWhere('agent2_id', '=', $params['agent_id']);
                });
            } elseif ($params['agent_id'] === 0) {
                $query->where('agent1_id', '=', 0);
            }
        }

        if (!empty($params['username'])) {
            $query->where('username', 'like', '%' . $params['username'] . '%');
        }

        $count = $query->count();
        $list = $query
            ->orderBy('id', 'desc')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get([
                'id',
                'username',
                'status',
                'expire_time',
                'note',
                'created_at',
                'agent1_id',
                'agent2_id',
            ])
            ->toArray();

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
}