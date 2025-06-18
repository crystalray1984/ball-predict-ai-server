<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 原始盘口表
 * @property int $id
 * @property int $match_id
 * @property string $crown_match_id
 * @property string $variety
 * @property string $period
 * @property string $type
 * @property string $condition
 * @property string $surebet_value
 * @property string $crown_value
 * @property string|null $crown_value2
 * @property string|null $crown_condition2
 * @property Carbon|null $ready_at
 * @property Carbon|null $final_at
 * @property string $status
 * @property string $final_rule
 * @property Carbon $surebet_updated_at
 * @property Carbon $crown_updated_at
 * @property int $is_open
 */
class Odd extends BaseModel
{
    protected $table = 'odd';

    const UPDATED_AT = null;

    protected $casts = [
        'ready_at' => 'datetime',
        'final_at' => 'datetime',
        'surebet_updated_at' => 'datetime',
    ];
}