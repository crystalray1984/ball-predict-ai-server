<?php declare(strict_types=1);

namespace support;

/**
 * 控制器基类
 */
abstract class Controller
{
    /**
     * 返回成功的响应数据
     * @param mixed|null $data
     * @return Response
     */
    protected function success(mixed $data = null): Response
    {
        return new JsonResponse([
            'code' => 0,
            'data' => $data,
        ]);
    }

    /**
     * 返回失败的响应数据
     * @param string $message
     * @param int $code
     * @return Response
     */
    protected function fail(string $message, int $code = 400): Response
    {
        return new JsonResponse([
            'code' => $code,
            'msg' => $message,
        ]);
    }
}