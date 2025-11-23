<?php declare(strict_types=1);

namespace app\middleware;

use app\model\Admin;
use support\attribute\CheckAdminToken;
use support\JsonResponse;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 检查管理员权限的中间件
 */
class CheckAdminTokenMiddleware extends CheckTokenMiddleware
{
    protected function getAttributeClass(): string
    {
        return CheckAdminToken::class;
    }

    protected function checkToken(int $id, Request $request, callable $handler, bool $optional): Response
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
}