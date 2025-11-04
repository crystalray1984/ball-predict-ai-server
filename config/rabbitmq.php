<?php declare(strict_types=1);

return [
    'host' => yaml('rabbitmq.master.hostname'),
    'port' => yaml('rabbitmq.master.port'),
    'vhost' => yaml('rabbitmq.master.vhost'),
    'login' => yaml('rabbitmq.master.username'),
    'password' => yaml('rabbitmq.master.password'),
];