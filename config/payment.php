<?php declare(strict_types=1);

return [
    //EDS正式链支付
    'endless' => [
        //收款地址
        'income_address' => '6jznefXVRvH7pS95otUE58bJtMDW6A8wRHSoqM2dJfmZ',
        'config' => [
            //日会员
            'day' => [
                //增加的会员时长
                'days' => 1,
                'price' => 180,
                'currency' => 'EDS',
            ],
            //周会员
            'week' => [
                //增加的会员时长
                'days' => 7,
                'price' => 1180,
                'currency' => 'EDS',
            ],
            //月会员
            'month' => [
                //增加的会员时长
                'days' => 30,
                'price' => 4200,
                'currency' => 'EDS',
            ],
        ],
    ],
    //EDS测试链支付
    'eds' => [
        //收款地址
        'income_address' => '8Lj8623aFoTNtY9gM81KGz9HxWotBEYnkMtwd1kqcLxW',
        'config' => [
            //日会员
            'day' => [
                //增加的会员时长
                'days' => 1,
                'price' => 1,
                'currency' => 'EDS',
            ],
            //周会员
            'week' => [
                //增加的会员时长
                'days' => 7,
                'price' => 2,
                'currency' => 'EDS',
            ],
            //月会员
            'month' => [
                //增加的会员时长
                'days' => 30,
                'price' => 3,
                'currency' => 'EDS',
            ],
        ],
    ],
    //Plisio USDT支付
    'plisio' => [
        //接口地址
        'channel_id' => yaml('plisio.name', 'admin@188zq.vip'),
        'api_url' => yaml('plisio.api_url', 'https://api.plisio.net/api/v1'),
        'secret' => yaml('plisio.secret', ''),
        'config' => [
            //日会员
            'day' => [
                //增加的会员时长
                'days' => 1,
                'price' => 5,
//                'price' => 18,
                'currency' => 'USDT',
            ],
            //周会员
            'week' => [
                //增加的会员时长
                'days' => 7,
                'price' => 5.1,
//                'price' => 120,
                'currency' => 'USDT',
            ],
            //月会员
            'month' => [
                //增加的会员时长
                'days' => 30,
                'price' => 5.2,
//                'price' => 420,
                'currency' => 'USDT',
            ],
        ],
    ],
];
