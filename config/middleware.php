<?php declare(strict_types=1);

return [
    '' => [
        \app\middleware\QueryLog::class,
        \app\middleware\CheckTokenMiddleware::class,
    ],
    'api' => [],
    'admin' => [],
    'agent' => []
];