<?php
return [
    'default' => 'master',
    'connections' => [
        'master' => [
            'driver' => 'pgsql',
            'host' => yaml('pgsql.host'),
            'port' => yaml('pgsql.port'),
            'database' => yaml('pgsql.database'),
            'username' => yaml('pgsql.username'),
            'password' => yaml('pgsql.password'),
            'charset' => 'utf8',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => [
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 0,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
