<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 用户佣金记录表
 * @property int $id
 * @property int $user_id
 * @property string $commission
 * @property int $order_id
 * @property Carbon $created_at
 * @property Carbon|null $settled_at
 */
class UserCommission extends BaseModel
{
    protected $table = 'user_commission';

    const UPDATED_AT = null;

    protected $casts = [
        'settled_at' => 'datetime',
    ];
}