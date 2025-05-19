<?php declare(strict_types=1);

return [
    //EDS正式链支付
    'endless' => [
        //收款地址
        'income_address' => '6jznefXVRvH7pS95otUE58bJtMDW6A8wRHSoqM2dJfmZ',
        'config' => [
            //周会员
            'week' => [
                //增加的会员时长
                'duration' => 7 * 86400,
                'price' => 1,
            ],
            //月会员
            'month' => [
                //增加的会员时长
                'duration' => 30 * 86400,
                'price' => 2,
            ],
            //年会员
            'year' => [
                //增加的会员时长
                'duration' => 365 * 86400,
                'price' => 3,
            ],
        ],
    ],
    //EDS测试链支付
    'eds' => [
        //收款地址
        'income_address' => '',
        'config' => [
            //周会员
            'week' => [
                //增加的会员时长
                'duration' => 7 * 86400,
                'price' => 1,
            ],
            //月会员
            'month' => [
                //增加的会员时长
                'duration' => 30 * 86400,
                'price' => 2,
            ],
            //年会员
            'year' => [
                //增加的会员时长
                'duration' => 365 * 86400,
                'price' => 3,
            ],
        ],
    ]
];