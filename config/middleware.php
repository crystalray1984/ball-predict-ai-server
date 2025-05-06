<?php declare(strict_types=1);

return [
    '' => [
        \app\middleware\QueryLog::class,
        \app\middleware\CheckUserTokenMiddleware::class,
        \app\middleware\CheckAdminTokenMiddleware::class,
        \app\middleware\CheckAgentTokenMiddleware::class,
    ],
    'api' => [],
    'admin' => [],
    'agent' => []
];