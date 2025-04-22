<?php declare(strict_types=1);

namespace support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

/**
 * Token操作类
 */
class Token
{
    /**
     * 创建JWT格式的token
     * @param array $payload
     * @param array $config
     * @return string
     */
    public static function create(array $payload, array $config = []): string
    {
        $config = array_deep_merge([], $config, config('token'));

        $claims = isset($config['claims']) && is_array($config['claims']) ? $config['claims'] : [];

        $now = time();

        $claims['payload'] = $payload;

        //签发时间
        if (!isset($claims['iat'])) {
            $claims['iat'] = $now;
        }

        //过期时间
        if (!isset($claims['exp']) && !empty($config['expire'])) {
            $claims['exp'] = $now + $config['expire'];
        }

        //合并头信息
        $headers = isset($config['headers']) && is_array($config['headers']) ? $config['headers'] : [];

        return JWT::encode(
            $claims,
            $config['key'],
            $config['algorithm'] ?? 'HS256',
            null,
            $headers
        );
    }

    /**
     * 解析JWT
     * @param string $jwt
     * @param array $headers
     * @return array
     */
    public static function decode(string $jwt, array &$headers = []): array
    {
        [$headersB64, $payloadB64] = explode('.', $jwt);
        $headers = json_decode(base64_decode($headersB64));
        return json_decode(base64_decode($payloadB64));
    }

    /**
     * 解析并校验JWT
     * @param string $jwt
     * @param array $config
     * @param array $headers
     * @return array
     * @throws UnexpectedValueException
     */
    public static function verify(string $jwt, array $config = [], array &$headers = []): array
    {
        $config += config('token', []);
        $headerObj = new \stdClass();
        $decoded = JWT::decode(
            $jwt,
            new Key($config['key'], $config['algorithm'] ?? 'HS256'),
            $headerObj,
        );

        $headers = json_decode(json_enc($headerObj), true);
        return json_decode(json_enc($decoded), true);
    }
}