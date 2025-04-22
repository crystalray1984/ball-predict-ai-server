<?php declare(strict_types=1);

return [
    'default' => [
        'password' => yaml('redis.password'),
        'host' => yaml('redis.host'),
        'port' => yaml('redis.port'),
        'database' => yaml('redis.database', 0),
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 0,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ]
];
