<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

class SurebetV2PromotedView extends BaseModel
{
    protected $table = 'v_surebet_v2_promoted';

    protected $casts = [
        'match_time' => 'datetime',
    ];
}