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
                'price' => 180,
                'currency' => 'EDS',
            ],
//            'eds' => [
//                'price' => 1,
//                'currency' => 'EDS',
//            ],
            'tron' => [
                'price' => 18,
                'currency' => 'USDT',
            ],
            'ethereum' => [
                'price' => 18,
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
                'price' => 1180,
                'currency' => 'EDS',
            ],
//            'eds' => [
//                'price' => 2,
//                'currency' => 'EDS',
//            ],
            'tron' => [
                'price' => 120,
                'currency' => 'USDT',
            ],
            'ethereum' => [
                'price' => 120,
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
                'price' => 4200,
                'currency' => 'EDS',
            ],
//            'eds' => [
//                'price' => 3,
//                'currency' => 'EDS',
//            ],
            'tron' => [
                'price' => 420,
                'currency' => 'USDT',
            ],
            'ethereum' => [
                'price' => 420,
                'currency' => 'USDT',
            ],
        ],
    ],
];
