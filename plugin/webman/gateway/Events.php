<?php declare(strict_types=1);

namespace plugin\webman\gateway;

use GatewayWorker\Lib\Gateway;
use support\Token;
use Throwable;

class Events
{
    public static function onWorkerStart($worker): void
    {

    }

    public static function onConnect(string $client_id): void
    {

    }

    public static function onWebSocketConnect(string $client_id, array $data): void
    {
        $get = $data['get'] ?? [];

        //检查token
        if (empty($get['token'])) {
            Gateway::closeClient($client_id);
            return;
        }

        //解析token
        try {
            $claims = Token::verify($get['token']);
        } catch (Throwable) {
            Gateway::closeClient($client_id);
            return;
        }

        if (empty($claims['payload']['type']) || empty($claims['payload']['id']) || $claims['payload']['type'] !== 'user') {
            Gateway::closeClient($client_id);
            return;
        }

        $user = get_user($claims['payload']['id']);
        if (!$user || $user->status !== 1) {
            Gateway::closeClient($client_id);
            return;
        }

        //设置连接的组
        Gateway::bindUid($client_id, $user->id);

        //加入vip组
        if ($user->expire_time->unix() > time()) {
            Gateway::joinGroup($client_id, 'vip');
        }
    }

    public static function onMessage(string $client_id, string $message): void
    {

    }

    public static function onClose(string $client_id): void
    {

    }
}
