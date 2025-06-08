<?php declare(strict_types=1);

namespace support\payment;

/**
 * EDS测试链支付引擎
 */
class EDS extends Endless
{
    public function __construct()
    {
        parent::__construct();

        //读取配置
        $config = config('payment_eds');
        $this->incomeAddress = $config['income_address'];
        $this->apiUrl = $config['api_url'];
    }
}