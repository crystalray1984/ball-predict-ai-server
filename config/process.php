<?php declare(strict_types=1);

use support\Log;
use support\Request;
use app\process\Http;

global $argv;

$processes = [
    'server' => [
        'handler' => Http::class,
        'listen' => 'http://0.0.0.0:8000',
        'count' => yaml('app.http_process_count', cpu_count() * 4),
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ]
];

if (yaml('app.monitor', false) === true) {
    $processes += [
        // File update detection and automatic reload
        'monitor' => [
            'handler' => app\process\Monitor::class,
            'reloadable' => false,
            'constructor' => [
                // Monitor these directories
                'monitorDir' => array_merge([
                    app_path(),
                    config_path(),
                    base_path() . '/process',
                    base_path() . '/support',
                    base_path() . '/resource',
                    base_path() . '/config.yaml',
                ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
                // Files with these suffixes will be monitored
                'monitorExtensions' => [
                    'php', 'yaml'
                ],
                'options' => [
                    'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                    'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
                ]
            ]
        ]
    ];
}

//luffa消息进程
//$processes['luffa_receiver'] = [
//    'handler' => \app\process\LuffaReceiver::class,
//    'count' => 1,
//    'reloadable' => true,
//    'constructor' => []
//];

//赛果异常检查
if (yaml('app.match_score_check', false) === true) {
    $processes['MatchScoreCheck'] = [
        'handler' => app\process\MatchScoreCheck::class,
        'count' => 1,
        'reloadable' => true,
        'constructor' => [],
    ];
}

return $processes;
