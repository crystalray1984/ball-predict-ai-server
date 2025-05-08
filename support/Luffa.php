<?php declare(strict_types=1);

namespace support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Luffa机器人
 */
class Luffa
{
    /**
     * Luffa机器人接口地址
     */
    const API_URL = 'https://apibot.luffa.im';

    /**
     * 调用Luffa机器人接口
     * @param string $url 接口地址
     * @param array $data 要发送的参数
     * @param array $options 其他扩展参数
     * @return array
     * @throws GuzzleException
     */
    protected static function request(string $url, array $data, array $options = [], bool $decode = false): mixed
    {
        $client = new Client();
        $options = [
                'base_uri' => static::API_URL,
                'json' => $data,
            ] + $options + [
                'timeout' => 10,
            ];

        $resp = $client->request('POST', $url, $options);
        if ($decode) {
            return json_decode($resp->getBody()->getContents(), true);
        }
        return null;
    }

    /**
     * 从Luffa获取发送给机器人的消息
     * @return array
     * @throws GuzzleException
     */
    public static function receive(): array
    {
        return static::request('/robot/receive', ['secret' => config('luffa.secret')], decode: true);
    }

    /**
     * 发送消息给个人
     * @param string $uid
     * @param string $text
     * @return void
     * @throws GuzzleException
     */
    public static function send(string $uid, string $text): void
    {
        static::request(
            '/robot/send',
            [
                'secret' => config('luffa.secret'),
                'uid' => $uid,
                'msg' => json_encode(['text' => $text]),
            ]
        );
    }

    /**
     * 发送消息到群组
     * @param string $uid
     * @param array $msg
     * @param int $type
     * @return void
     * @throws GuzzleException
     */
    public static function sendGroup(string $uid, array $msg, int $type): void
    {
        static::request(
            '/robot/sendGroup',
            [
                'secret' => config('luffa.secret'),
                'uid' => $uid,
                'msg' => $msg,
                'type' => $type,
            ]
        );
    }
}