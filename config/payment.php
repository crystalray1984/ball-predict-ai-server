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
                'duration' => 86400,
                'price' => 180
            ],
            //周会员
            'week' => [
                //增加的会员时长
                'duration' => 7 * 86400,
                'price' => 1180,
            ],
            //月会员
            'month' => [
                //增加的会员时长
                'duration' => 30 * 86400,
                'price' => 4200,
            ],
            //季度会员
            'quarter' => [
                //增加的会员时长
                'duration' => 90 * 86400,
                'price' => 11880,
            ],
        ],
    ],
    //EDS测试链支付
    'eds' => [
        //收款地址
        'income_address' => '',
        'config' => [
            //日会员
            'day' => [
                //增加的会员时长
                'duration' => 86400,
                'price' => 1,
            ],
            //周会员
            'week' => [
                //增加的会员时长
                'duration' => 7 * 86400,
                'price' => 2,
            ],
            //月会员
            'month' => [
                //增加的会员时长
                'duration' => 30 * 86400,
                'price' => 3,
            ],
            //季度会员
            'quarter' => [
                //增加的会员时长
                'duration' => 90 * 86400,
                'price' => 4,
            ],
        ],
    ]
];