<?php declare(strict_types=1);

namespace app\middleware;

use app\model\Admin;
use app\model\Agent;
use app\model\User;
use Exception;
use support\attribute\CheckToken;
use support\JsonResponse;
use support\Token;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 检查token的中间件
 */
class CheckTokenMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        //先判断是否为控制器和方法组合
        if (!$request->controller || !$request->action) {
            return $handler($request);
        }

        //再检查方法上是否存在检查token的注解
        $attrs = CheckToken::getAllAttributes($request->controller, $request->action);
        if (empty($attrs)) {
            //不需要检查token
            return $handler($request);
        }

        //再判断是否在请求头中有token
        $token = $request->header('authorization', '');
        if (empty($token)) {
            return $this->fail();
        }

        if (!preg_match('/^Bearer\s(\S+)$/', $token, $matches)) {
            return $this->fail();
        }

        $token = $matches[1];

        //解析token
        try {
            $claims = Token::verify($token);
        } catch (Exception) {
            return $this->fail();
        }

        //检查是否存在与token类型对应的注解
        $attr = array_find($attrs, fn(CheckToken $attr) => $attr->type === $claims['payload']['type']);
        if (!$attr) {
            return $this->fail();
        }

        //根据不同的token类型做检查
        return match ($attr->type) {
            'user' => $this->checkUserToken($claims['payload']['id'], $request, $handler),
            'agent' => $this->checkAgentToken($claims['payload']['id'], $request, $handler),
            'admin' => $this->checkAdminToken($claims['payload']['id'], $request, $handler),
            default => $this->fail(),
        };
    }

    /**
     * 检查用户token
     * @param int $id
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    protected function checkUserToken(int $id, Request $request, callable $handler): Response
    {
        /**
         * @var User $user
         */
        $user = get_user($id);

        if (!$user) {
            //用户不存在
            return $this->fail();
        }

        if ($user->status !== 1) {
            //用户已禁用
            return new JsonResponse([
                'code' => 402,
                'msg' => '账户已被禁用',
            ]);
        }

        //设置实体上的用户信息
        if ($request instanceof \support\Request) {
            $request->user = $user;
        }

        return $handler($request);
    }

    /**
     * 检查代理token
     * @param int $id
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    protected function checkAgentToken(int $id, Request $request, callable $handler): Response
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

    /**
     * 检查管理员token
     * @param int $id
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    protected function checkAdminToken(int $id, Request $request, callable $handler): Response
    {
        /**
         * @var Admin $admin
         */
        $admin = get_admin($id);

        if (!$admin) {
            //不存在
            return $this->fail();
        }

        if ($admin->status !== 1) {
            //用户已禁用
            return new JsonResponse([
                'code' => 402,
                'msg' => '账户已被禁用',
            ]);
        }

        //设置实体上的用户信息
        if ($request instanceof \support\Request) {
            $request->admin = $admin;
        }

        return $handler($request);
    }

    /**
     * 返回未登录的响应
     * @return Response
     */
    protected function fail(): Response
    {
        return new JsonResponse([
            'code' => 401,
            'msg' => '未登录'
        ]);
    }
}