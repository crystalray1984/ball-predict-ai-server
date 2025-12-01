<?php declare(strict_types=1);

namespace plugin\webman\gateway;

use GatewayWorker\Lib\Gateway;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;
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
                self::checkUserConnect($client_id, $data);
                $valid = true;

                //加入设备组，监听更新消息
                if (!empty($data['get']['platform'])) {
                    if ($data['get']['platform'] === 'win32') {
                        Gateway::joinGroup($client_id, 'platform:win32:' . $data['get']['arch']);
                    } else if ($data['get']['platform'] === 'darwin') {
                        Gateway::joinGroup($client_id, 'platform:darwin');
                    }
                }
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
        //设置连接的类型标识
        Gateway::updateSession($client_id, ['type' => 'user']);

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
        //设置连接的类型标识
        Gateway::updateSession($client_id, ['type' => 'service']);
        return true;
    }

    public static function onMessage(string $client_id, string $message): void
    {
        //解析消息
        $data = json_decode($message, true);
        if (empty($data) || !empty($data['type'])) return;

        //检查消息连接上的
        $checkClientType = fn(string $type) => !empty($_SESSION['type']) && $_SESSION['type'] === $type;

        //根据不同的消息类型，进行不同的处理
        switch ($data['type']) {
            case 'send_to': //发送消息到其他客户端
                //需要检查只有服务客户端允许发消息
                if ($checkClientType('service')) {
                    self::doSendTo($client_id, $data);
                }
                break;
            default:
                break;
        }
    }

    /**
     * 执行消息发送操作
     * @param string $client_id
     * @param array $data
     * @return void
     */
    protected static function doSendTo(string $client_id, array $data): void
    {
        //消息格式检测
        try {
            $params = v::input($data['data'], [
                'type' => v::in(['uid', 'group'])->setName('type'),
                'target' => v::anyOf(
                    v::arrayType()->notEmpty(),
                    v::stringType()->notEmpty(),
                    v::intType()->positive(),
                )->setName('target'),
                'message' => v::arrayType()
                    ->key('type', v::stringType()->notEmpty())
                    ->setName('message'),
            ]);
        } catch (ValidationException) {
            return;
        }

        $message = json_enc($params['message']);

        //发送
        switch ($params['type']) {
            case 'uid':
                Gateway::sendToUid($params['target'], $message);
                break;
            case 'group':
                Gateway::sendToGroup($params['target'], $message, [$client_id]);
                break;
        }
    }

    public static function onClose(string $client_id): void
    {

    }
}
