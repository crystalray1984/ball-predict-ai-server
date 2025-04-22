<?php declare(strict_types=1);

namespace app\api\middleware;

use app\model\User;
use Exception;
use support\attribute\AllowGuest;
use support\JsonResponse;
use support\Token;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 检测用户登录状态的中间件
 */
class CheckUserMiddleware implements MiddlewareInterface
{
    /**
     *
     */
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

        if (empty($claims['payload']['id'])) {
            //没有用户id
            return $this->fail();
        }

        //检查用户信息
        /** @var User $user */
        $user = User::query()->where('id', '=', $claims['payload']['id'])->first();
        if (!$user || $user->status !== 1 || $user->expire_time->timestamp < time()) {
            return $this->fail();
        }

        //将用户实体写入请求中
        if ($request instanceof \support\Request) {
            $request->user = $user;
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