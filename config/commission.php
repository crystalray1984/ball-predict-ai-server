<?php declare(strict_types=1);

//佣金配置
return [
    //从用户支付金额中获得的佣金比例
    'ratio' => '0.3',
    //提现通道
    'channels' => [
        'tron' => [
            //最小提现佣金金额(USDT)
            'min_amount' => 100,
        ],
        'ethereum' => [
            //最小提现佣金金额(USDT)
            'min_amount' => 100,
        ],
    ]
];
