<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 用户提现记录表
 *
 * @property int $id
 * @property int $user_id
 * @property string $amount
 * @property string $channel_type
 * @property string $withdrawal_account
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $finished_at
 */
class UserWithdrawal extends BaseModel
{
    protected $table = 'user_withdrawal';

    protected $casts = [
        'finished_at' => 'datetime',
    ];
}