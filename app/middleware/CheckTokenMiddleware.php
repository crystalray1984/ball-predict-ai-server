<?php declare(strict_types=1);

namespace app\middleware;

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
abstract class CheckTokenMiddleware implements MiddlewareInterface
{
    protected abstract function getAttributeClass(): string;

    protected abstract function checkToken(int $id, Request $request, callable $handler): Response;

    public function process(Request $request, callable $handler): Response
    {
        //先判断是否为控制器和方法组合
        if (!$request->controller || !$request->action) {
            return $handler($request);
        }

        //再检查方法上是否存在检查token的注解
        $attrClass = $this->getAttributeClass();
        $attrs = call_user_func([$attrClass, 'getAllAttributes'], $request->controller, $request->action);

        echo $request->path() . PHP_EOL;
        var_dump($attrs);

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
            return $handler($request);
        }

        return $this->checkToken($claims['payload']['id'], $request, $handler);
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