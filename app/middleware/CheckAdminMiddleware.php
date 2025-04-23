<?php declare(strict_types=1);

namespace app\middleware;

use app\model\Admin;
use Exception;
use support\attribute\AllowGuest;
use support\JsonResponse;
use support\Token;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class CheckAdminMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        //先判断是否为控制器和方法组合
        if (!$request->controller || !$request->action) {
            return $handler($request);
        }

        //判断接口或者控制器上是否存在允许游客访问的注解
        if (AllowGuest::getAttribute($request->controller, $request->action)) {
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

        if (empty($claims['payload']['id']) || empty($claims['payload']['type']) || $claims['payload']['type'] !== 'admin') {
            //无效的用户id
            return $this->fail();
        }

        //检查管理员信息
        /** @var Admin $admin */
        $admin = Admin::query()->where('id', '=', $claims['payload']['id'])->first();
        if (!$admin || $admin->status !== 1) {
            return $this->fail();
        }

        //将用户实体写入请求中
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