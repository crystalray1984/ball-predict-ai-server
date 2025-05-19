<?php
return [
    'default' => 'master',
    'connections' => [
        'master' => [
            'driver' => 'mysql',
            'host' => yaml('mysql.master.host'),
            'port' => yaml('mysql.master.port'),
            'database' => yaml('mysql.master.database'),
            'username' => yaml('mysql.master.username'),
            'password' => yaml('mysql.master.password'),
            'charset' => 'utf8mb4',
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
                'heartbeat_interval' => 35,
            ],
        ],
    ],
];
