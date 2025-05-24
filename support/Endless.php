<?php declare(strict_types=1);

namespace support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class Endless
{
    public readonly string $network;

    const API_URL = [
        'endless' => 'https://rpc.endless.link',
        'eds' => 'https://rpc-test.endless.link',
    ];

    protected function __construct(string $network)
    {
        if (empty(self::API_URL[$network])) {
            throw new RuntimeException('无效的网络类型');
        }
        $this->network = $network;
    }

    public static function create(string $network): self
    {
        return new self($network);
    }

    /**
     * 根据hash获取订单信息
     * @param string $hash
     * @return array
     */
    public function getTransaction(string $hash): array
    {
        $client = new Client();
        $retry = 10;
        while (true) {
            try {
                $resp = $client->get(
                    self::API_URL[$this->network] . '/v1/transactions/by_hash/' . $hash,
                    [
                        'timeout' => 10,
                    ]
                );
                if ($resp->getStatusCode() === 404) {
                    return [];
                }
                if ($resp->getStatusCode() === 200) {
                    return json_decode($resp->getBody()->getContents(), true);
                }
            } catch (GuzzleException $e) {
                $retry--;
                if ($retry > 0) {
                    Log::error($e->getMessage());
                    sleep(5);
                    continue;
                }
                throw $e;
            }
        }

    }
}