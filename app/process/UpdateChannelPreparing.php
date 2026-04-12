<?php declare(strict_types=1);

namespace app\process;

use app\api\service\DataService;
use support\Redis;
use Workerman\Timer;

/**
 * 自动生成频道测算中数据的进程
 */
class UpdateChannelPreparing
{
    public function onWorkerStart(): void
    {
        $service = G(DataService::class);

        Timer::add(10, function () use (&$service) {
            $rockball = $service->rockballPreparing('rockball');
            Redis::setEx("preparing:rockball", 300, json_enc($rockball));
            $rockball2 = $service->rockballPreparing('rockball2');
            Redis::setEx("preparing:rockball2", 300, json_enc($rockball2));
            $rockball3 = $service->rockballPreparing('rockball3');
            Redis::setEx("preparing:rockball3", 300, json_enc($rockball3));
            $rockball4 = $service->rockballPreparing('rockball4');
            Redis::setEx("preparing:rockball4", 300, json_enc($rockball4));
            $rockball5 = $service->rockballPreparing('rockball5');
            Redis::setEx("preparing:rockball5", 300, json_enc($rockball5));
            $mansion = $service->mansionPreparing();
            Redis::setEx("preparing:mansion", 300, json_enc($mansion));
        });
    }
}