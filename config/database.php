<?php
return [
    'default' => 'master',
    'connections' => [
        'master' => [
            'driver' => 'pgsql',
            'host' => yaml('pgsql.master.host'),
            'port' => yaml('pgsql.master.port'),
            'database' => yaml('pgsql.master.database'),
            'username' => yaml('pgsql.master.username'),
            'password' => yaml('pgsql.master.password'),
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
                'heartbeat_interval' => 35,
            ],
        ],
    ],
];
