<?php declare(strict_types=1);

use support\Request;

return [
    'debug' => false,
    'error_reporting' => E_ALL,
    'default_timezone' => 'Asia/Shanghai',
    'request_class' => Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => '',
    'controller_reuse' => true,
    //新用户获得的免费VIP时长
    'new_user_expires' => 7200,
];
