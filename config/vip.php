<?php declare(strict_types=1);

//VIP配置
return [
    //日卡
    'day' => [
        //增加的天数
        'days' => 1,
        //价格配置
        'price' => [
            'endless' => [
                'price' => 1800,
                'currency' => 'EDS',
            ],
            'bsc' => [
                'price' => 180,
                'currency' => 'EDS',
            ],
            'tron' => [
                'price' => 180,
                'currency' => 'USDT',
            ],
            'ethereum' => [
                'price' => 180,
                'currency' => 'USDT',
            ],
        ],
    ],
    //周卡
    'week' => [
        //增加的天数
        'days' => 7,
        //价格配置
        'price' => [
            'endless' => [
                'price' => 11800,
                'currency' => 'EDS',
            ],
            'bsc' => [
                'price' => 1200,
                'currency' => 'EDS',
            ],
            'tron' => [
                'price' => 1200,
                'currency' => 'USDT',
            ],
            'ethereum' => [
                'price' => 1200,
                'currency' => 'USDT',
            ],
        ],
    ],
    //月卡
    'month' => [
        //增加的天数
        'days' => 30,
        //价格配置
        'price' => [
            'endless' => [
                'price' => 42000,
                'currency' => 'EDS',
            ],
            'bsc' => [
                'price' => 4200,
                'currency' => 'EDS',
            ],
            'tron' => [
                'price' => 4200,
                'currency' => 'USDT',
            ],
            'ethereum' => [
                'price' => 4200,
                'currency' => 'USDT',
            ],
        ],
    ],
];
