<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 用户佣金变更记录表
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $amount
 * @property string $amount_after
 * @property Carbon $created_at
 */
class UserCommissionRecord extends BaseModel
{
    protected $table = 'user_commission_record';

    const UPDATED_AT = null;
}