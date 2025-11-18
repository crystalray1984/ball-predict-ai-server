<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

class PromotedOddMansionView extends BaseModel
{
    protected $table = 'v_promoted_odd_mansion';

    protected $casts = [
        'match_time' => 'datetime',
    ];
}