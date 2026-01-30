<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * Bmiss提现记录
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
class BmissWithdrawal extends BaseModel
{
    protected $table = 'bmiss_withdrawal';

    const UPDATED_AT = null;

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}