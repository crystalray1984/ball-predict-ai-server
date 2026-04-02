<?php declare(strict_types=1);

namespace app\process;

use app\api\service\DataService;
use support\Redis;
use Workerman\Timer;

/**
 * 自动生成频道统计数据的进程
 */
class UpdateChannelSummary
{
    public function onWorkerStart(): void
    {
        $service = G(DataService::class);

        Timer::add(10, function () use (&$service) {
            $channels = array_column(config('channel'), 'key');
            foreach ($channels as $channel) {
                $summary = $service->summary([$channel]);
                Redis::setEx("summary:$channel", 300, json_enc($summary));
            }
        });
    }
}