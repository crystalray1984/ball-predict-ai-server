<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

/**
 * 滚球盘推荐视图
 */
class RockBallPromotedView extends BaseModel
{
    protected $table = 'v_rockball_promoted';

    protected $casts = [
        'match_time' => 'datetime',
    ];
}