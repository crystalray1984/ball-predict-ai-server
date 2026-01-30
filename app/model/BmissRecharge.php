<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * Bmiss充值记录
 * @property int $id
 * @property int $user_id
 * @property string $appid
 * @property string $openid
 * @property int $amount
 * @property int $status
 * @property Carbon $created_at
 * @property string $bmiss_order_no
 * @property Carbon|null $completed_at
 * @property string $bmiss_order_info
 */
class BmissRecharge extends BaseModel
{
    protected $table = 'bmiss_recharge';

    const UPDATED_AT = null;

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}