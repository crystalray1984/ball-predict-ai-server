<?php declare(strict_types=1);

return [
    '' => [
        \app\middleware\QueryLog::class,
    ],
    'api' => [
        \app\middleware\CheckUserMiddleware::class,
    ]
];