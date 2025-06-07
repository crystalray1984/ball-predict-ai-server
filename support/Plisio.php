<?php declare(strict_types=1);

namespace support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Plisio支付通道支持
 */
class Plisio
{
    public readonly string $api_url;

    public readonly string $secret;

    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config = config('payment.plisio');
        }
        $this->api_url = $config['api_url'];
        $this->secret = $config['secret'];
    }

    /**
     * 调用接口
     * @param string $url
     * @param array $params
     * @param array $options
     * @return array
     * @throws GuzzleException
     */
    protected function api(string $url, array $params = [], array $options = []): array
    {
        $params['api_key'] = $this->secret;

        $client = new Client([
            'base_uri' => $this->api_url,
        ]);

        $options[RequestOptions::QUERY] = $params;

        //创建一个唯一的请求id
        $request_id = uniqid();

        //记录请求数据
        Log::channel('plisio')->debug("[$request_id] " . $url);
        Log::channel('plisio')->debug("[$request_id] " . json_enc($params));

        try {
            $resp = $client->request('GET', $url, $options);
        } catch (GuzzleException $e) {
            //记录plisio异常
            Log::channel('plisio')->error("[$request_id] " . $e);
            throw $e;
        }

        //记录响应数据
        $respContent = $resp->getBody()->getContents();
        Log::channel('plisio')->debug("[$request_id] " . $respContent);

        return json_decode($respContent, true);
    }

    /**
     * 创建订单
     * @param array $params 订单参数
     * @return array
     * @throws GuzzleException
     */
    public function createInvoice(
        array $params
    ): array
    {
        //生成订单
        return $this->api('/invoices/new', $params);
    }

    /**
     * 获取交易详情
     * @param string $id
     * @return array
     * @throws GuzzleException
     */
    public function getTransaction(string $id): array
    {
        return $this->api("/operations/$id");
    }
}