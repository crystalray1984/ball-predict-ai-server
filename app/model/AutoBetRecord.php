<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 自动投注记录表
 * @property int $id
 * @property int $user_id
 * @property string $crown_uid
 * @property int $promote_id
 * @property string $bet_value
 * @property string $bet_amount
 * @property Carbon $created_at
 * @property array $extra
 */
class AutoBetRecord extends BaseModel
{
    protected $table = 'auto_bet_record';

    const UPDATED_AT = null;

    protected $casts = [
        'extra' => 'array',
    ];
}