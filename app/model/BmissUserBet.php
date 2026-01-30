<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * Bmiss用户投注记录表
 * @property int $id
 * @property int $user_id
 * @property string $openid
 * @property string $appid
 * @property int $match_id
 * @property string $base
 * @property string $type
 * @property string $condition
 * @property string $value
 * @property int $amount
 * @property Carbon $created_at
 * @property int|null $result
 * @property int $result_amount
 * @property string|null $result_status
 * @property Carbon|null $settlement_at
 * @property string $result_text
 */
class BmissUserBet extends BaseModel
{
    protected $table = 'bmiss_user_bet';

    const UPDATED_AT = null;

    protected $casts = [
        'settlement_at' => 'datetime',
    ];
}