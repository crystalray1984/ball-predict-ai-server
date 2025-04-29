<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Agent;
use support\exception\BusinessError;

/**
 * 代理业务逻辑
 */
class AgentService
{
    /**
     * 获取代理列表
     * @param array $params
     * @return array
     */
    public function getList(array $params): array
    {
        $query = Agent::query();
        if (isset($params['parent_id']) && $params['parent_id'] >= 0) {
            $query->where('parent_id', '=', $params['parent_id']);
        }

        return $query
            ->orderBy('id', 'desc')
            ->get([
                'id',
                'parent_id',
                'username',
                'code',
                'status',
                'note'
            ])
            ->toArray();
    }

    /**
     * 获取代理详情
     * @param int $id
     * @return array
     */
    public function getDetails(int $id): array
    {
        $agent = Agent::query()
            ->where('id', '=', $id)
            ->first([
                'id',
                'parent_id',
                'username',
                'code',
                'status',
                'note',
                'commission_config',
            ]);
        if (!$agent) {
            throw new BusinessError('未找到数据');
        }

        $agent = $agent->toArray();
        $agent['password'] = '';
        $agent['commission_config'] = json_decode($agent['commission_config'], true);
        return $agent;
    }

    /**
     * 保存代理
     * @param array $data
     * @return void
     */
    public function save(array $data): void
    {
        if (!empty($data['id'])) {
            $agent = Agent::query()->where('id', '=', $data['id'])->first();
            if (!$agent) {
                throw new BusinessError('未找到要编辑的数据');
            }
        } else {
            $agent = new Agent();

            //检查上级id
            if (!empty($data['parent_id'])) {
                $parent = Agent::query()
                    ->where('id', '=', $data['parent_id'])
                    ->where('parent_id', '=', 0)
                    ->exists();
                if (!$parent) {
                    throw new BusinessError('无效的上级id');
                }
                $agent->parent_id = $data['parent_id'];
            } else {
                $agent->parent_id = 0;
            }

            //生成短id
            while (true) {
                $code = strtoupper(uniqid());
                $exists = Agent::query()->where('code', '=', $code)->exists();
                if (!$exists) {
                    $agent->code = $code;
                    break;
                }
            }
        }

        $agent->username = $data['username'];
        if (!empty($agent['password'])) {
            $agent->password = md5($agent['password']);
        }
        $agent->status = $data['status'];
        $agent->save();
    }
}