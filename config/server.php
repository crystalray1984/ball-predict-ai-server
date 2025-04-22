<?php declare(strict_types=1);

return [
    'event_loop' => '',
    'stop_timeout' => 2,
    'pid_file' => runtime_path() . '/server.pid',
    'status_file' => runtime_path() . '/server.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'log_file' => runtime_path() . '/logs/server.log',
    'max_package_size' => 10 * 1024 * 1024
];
