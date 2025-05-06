<?php declare(strict_types=1);

namespace app\middleware;

use app\model\Agent;
use support\attribute\CheckAgentToken;
use support\JsonResponse;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 检查代理商权限的中间件
 */
class CheckAgentTokenMiddleware extends CheckTokenMiddleware
{
    protected function getAttributeClass(): string
    {
        return CheckAgentToken::class;
    }

    protected function checkToken(int $id, Request $request, callable $handler): Response
    {
        /**
         * @var Agent $agent
         */
        $agent = get_agent($id);

        if (!$agent) {
            //不存在
            return $this->fail();
        }

        if ($agent->status !== 1) {
            //用户已禁用
            return new JsonResponse([
                'code' => 402,
                'msg' => '账户已被禁用',
            ]);
        }

        //二级代理需要检查一级代理的状态
        if ($agent->parent_id > 0) {
            $parent = get_agent($agent->parent_id);
            if (!$parent) {
                return $this->fail();
            }
            if ($parent->status !== 1) {
                return new JsonResponse([
                    'code' => 402,
                    'msg' => '账户已被禁用',
                ]);
            }
        }

        //设置实体上的用户信息
        if ($request instanceof \support\Request) {
            $request->agent = $agent;
        }

        return $handler($request);
    }
}