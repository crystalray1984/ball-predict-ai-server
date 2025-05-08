<?php declare(strict_types=1);

namespace app\process;

use app\model\LuffaMsg;
use support\Log;
use support\Luffa;
use Throwable;
use Workerman\Timer;

/**
 * 接收并处理Luffa消息的进程
 */
class LuffaReceiver
{
    public function onWorkerStart(): void
    {
        Timer::add(0.01, function () {
            $this->onTick();
        }, [], false);
    }

    protected function onTick(): void
    {
        try {
            $this->receive();
        } catch (Throwable $e) {
            Log::error((string)$e);
        }

        Timer::add(5, function () {
            $this->onTick();
        }, [], false);
    }

    /**
     * 接收消息
     * @return void
     */
    public function receive(): void
    {
        //从luffa服务器读取消息列表
        $data = Luffa::receive();
        if (empty($data)) return;

        //需要回复给个人的消息
        $reply = [];

        $to_uid = config('luffa.uid', '');

        //解析消息
        foreach ($data as $group) {
            $from_uid = $group['uid'];
            $type = (int)$group['type'];

            //解析消息列表
            foreach ($group['message'] as $msg_str) {
                $msg = json_decode($msg_str, true);
                if (!empty($msg['msgId'])) {
                    //如果有消息id就需要去重
                    $exists = LuffaMsg::query()
                        ->where('msg_id', '=', $msg['msgId'])
                        ->exists();
                    if ($exists) continue;
                } else {
                    //没有msgId的消息
                    if ($type === 0 && !in_array($from_uid, $reply)) {
                        $reply[] = $from_uid;
                    }
                    continue;
                }

                //把消息写入表
                LuffaMsg::insert([
                    'msg_id' => $msg['msgId'],
                    'from_uid' => $from_uid,
                    'to_uid' => $to_uid,
                    'type' => $type,
                    'content' => $msg_str,
                ]);
            }
        }
    }
}