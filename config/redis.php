<?php declare(strict_types=1);

return [
    'default' => [
        'host' => yaml('redis.master.host'),
        'port' => yaml('redis.master.port'),
        'password' => yaml('redis.master.password'),
        'database' => yaml('redis.master.database', 0),
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 0,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 35,
        ],
    ]
];
