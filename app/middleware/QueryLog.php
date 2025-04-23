<?php declare(strict_types=1);

namespace app\middleware;

use support\JsonResponse;
use support\QueryLogger;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 输出查询历史的调试中间件
 */
class QueryLog implements MiddlewareInterface
{
    /**
     * @param \support\Request $request
     * @param callable $handler
     * @return Response
     */
    public function process(Request $request, callable $handler): Response
    {
        //只在调试开关开启的时候启用
        if (!enable_debug($request)) {
            return $handler($request);
        }

        $logs = [];
        $listener = function (array $log) use (&$logs) {
            $logs[] = $log;
        };
        $resp = QueryLogger::capture(fn() => $handler($request), $listener);
        if ($resp instanceof JsonResponse) {
            $data = $resp->getJsonBody();
            if (is_array($data)) {
                $data['_query'] = $logs;
                $resp = $resp->withJsonBody($data);
            }
        }
        return $resp;
    }
}