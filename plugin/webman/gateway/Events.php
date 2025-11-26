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
        if (empty($data['get']['type'])) {
            Gateway::sendToClient($client_id, json_enc(['type' => 'error', 'msg' => 'unknown connection']));
            Gateway::closeClient($client_id);
            return;
        }

        $valid = false;
        switch ($data['get']['type']) {
            case 'user':
                $valid = self::checkUserConnect($client_id, $data);
                break;
            case 'service':
                $valid = self::checkServiceConnect($client_id, $data);
                break;
        }
        if (!$valid) {
            Gateway::sendToClient($client_id, json_enc(['type' => 'error', 'msg' => 'invalid connection']));
            Gateway::closeClient($client_id);
        }
    }

    /**
     * 检查客户端连接
     * @param string $client_id
     * @param array $data
     * @return bool
     */
    public static function checkUserConnect(string $client_id, array $data): bool
    {
        $get = $data['get'] ?? [];

        //检查token
        if (empty($get['token'])) {
            return false;
        }

        //解析token
        try {
            $claims = Token::verify($get['token']);
        } catch (Throwable) {
            return false;
        }

        if (empty($claims['payload']['type']) || empty($claims['payload']['id']) || $claims['payload']['type'] !== 'user') {
            return false;
        }

        $user = get_user($claims['payload']['id']);
        if (!$user || $user->status !== 1) {
            return false;
        }

        //设置连接的组
        Gateway::bindUid($client_id, $user->id);

        //加入vip组
        if ($user->expire_time->unix() > time()) {
            Gateway::joinGroup($client_id, 'vip');
        }

        return true;
    }

    /**
     * 检查服务端连接
     * @param string $client_id
     * @param array $data
     * @return bool
     */
    public static function checkServiceConnect(string $client_id, array $data): bool
    {
        if (!empty($data['get']['service_type'])) {
            Gateway::joinGroup($client_id, $data['get']['service_type']);
        }
        return true;
    }

    public static function onMessage(string $client_id, string $message): void
    {

    }

    public static function onClose(string $client_id): void
    {

    }
}
