<?php declare(strict_types=1);

namespace support\payment\trait;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Plisio\ClientAPI;
use support\Log;

/**
 * Plisio支付支持
 */
trait Plisio
{
    public string $email;

    public string $secret;

    protected string $apiUrl = 'https://api.plisio.net/api/v1';

    /**
     * 调用接口
     * @param string $url
     * @param array $params
     * @param array $options
     * @return array
     * @throws GuzzleException
     */
    protected function call(string $url, array $params = [], array $options = []): array
    {
        $params['api_key'] = $this->secret;

        $client = new Client();

        $options[RequestOptions::QUERY] = $params;

        //创建一个唯一的请求id
        $request_id = uniqid();

        //记录请求数据
        Log::channel('plisio')->debug("[$request_id] " . $url);
        Log::channel('plisio')->debug("[$request_id] " . json_enc($params));

        try {
            $resp = $client->request('GET', $this->apiUrl . $url, $options);
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
     * @param array $data
     * @return array
     * @throws GuzzleException
     */
    protected function createInvoice(array $data): array
    {
        return $this->call('/invoices/new', $data);
    }

    /**
     * 读取订单数据
     * @param string $txn_id
     * @return array
     * @throws GuzzleException
     */
    protected function getTransaction(string $txn_id): array
    {
        return $this->call("/operations/$txn_id");
    }

    /**
     * 校验交易回调数据
     * @param array $data
     * @return bool
     */
    public function verifyCallback(array $data): bool
    {
        return (new ClientAPI($this->secret))->verifyCallbackData($data, $this->secret);
    }
}