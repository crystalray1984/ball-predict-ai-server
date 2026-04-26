<?php declare(strict_types=1);

/**
 * 频道设定
 */
return [
//    [
//        'key' => 'rockball',
//        'name' => '滚球',
//        'report' => true,
//        'filter' => 'rockball_filter',
//    ],
//    [
//        'key' => 'rockball2',
//        'name' => '滚球2',
//        'report' => true,
//    ],
//    [
//        'key' => 'rockball3',
//        'name' => '滚球3',
//        'use_profit' => true,
//    ],
//    [
//        'key' => 'rockball4',
//        'name' => '滚球4',
//        'use_profit' => true,
//    ],
    [
        'key' => 'ai_ah',
        'name' => '让球',
        'type_list' => ['ah1', 'ah2'],
    ],
    [
        'key' => 'ai_sum',
        'name' => '大小球',
        'type_list' => ['over', 'under'],
    ],
//    [
//        'key' => 'ai_win',
//        'name' => '胜平负',
//    ],
    [
        'key' => 'ai_btts',
        'name' => '双方进球',
        'type_list' => ['btts_yes', 'btts_no'],
    ],
    [
        'key' => 'rockball5',
        'name' => '滚球',
//        'use_profit' => true,
    ],
//    [
//        'key' => 'direct',
//        'name' => '模型1',
//    ],
//    [
//        'key' => 'mansion',
//        'name' => '模型2',
//    ],
    [
        'key' => 'model3',
        'name' => '模型3',
        'use_profit' => true,
        'type_list' => ['over', 'under'],
    ],
];
