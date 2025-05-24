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
            //季度会员
            'quarter' => [
                //增加的会员时长
                'days' => 90,
                'price' => 11880,
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
            //季度会员
            'quarter' => [
                //增加的会员时长
                'days' => 90,
                'price' => 4,
                'currency' => 'EDS',
            ],
        ],
    ]
];
