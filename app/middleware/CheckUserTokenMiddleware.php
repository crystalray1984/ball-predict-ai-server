<?php declare(strict_types=1);

namespace app\middleware;

use app\model\User;
use support\attribute\CheckUserToken;
use support\JsonResponse;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 检查用户权限的中间件
 */
class CheckUserTokenMiddleware extends CheckTokenMiddleware
{
    /**
     * 检查用户token
     * @param int $id
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    protected function checkToken(int $id, Request $request, callable $handler): Response
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


    protected function getAttributeClass(): string
    {
        return CheckUserToken::class;
    }
}