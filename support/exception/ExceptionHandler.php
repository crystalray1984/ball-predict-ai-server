<?php declare(strict_types=1);

namespace support\exception;

use Respect\Validation\Exceptions\ValidationException;
use support\JsonResponse;
use Throwable;
use Webman\Exception\ExceptionHandler as BaseHandler;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 接口异常捕获对象
 */
class ExceptionHandler extends BaseHandler
{
    public $dontReport = [
        BusinessError::class,
        ValidationException::class,
    ];

    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof BusinessError) {
            //业务异常
            return new JsonResponse([
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage(),
            ]);
        }

        if ($exception instanceof ValidationException) {
            //参数校验异常
            return new JsonResponse([
                'code' => 100,
                'msg' => $exception->getMessage(),
            ]);
        }

        $json = [
            'code' => 500,
        ];

        if (enable_debug($request)) {
            //显示原始的错误信息
            $json['msg'] = $exception->getMessage();
            $json['_trace'] = explode("\n", $exception->getTraceAsString());
        } else {
            //显示安全信息
            $json['msg'] = '系统异常';
        }

        return new JsonResponse($json);
    }
}