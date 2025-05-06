<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;
use support\Model;

/**
 * 原始盘口表
 * @property int $id
 * @property int $match_id
 * @property int $crown_match_id
 * @property string $game
 * @property string $base
 * @property string $variety
 * @property string $period
 * @property string $type
 * @property string $condition
 * @property string $surebet_value
 * @property string $crown_value
 * @property string $status
 * @property Carbon $surebet_updated_at
 * @property Carbon $crown_updated_at
 */
class Odd extends BaseModel
{
    protected $table = 'odd';

    const UPDATED_AT = null;

    protected $casts = [
        'surebet_updated_at' => 'datetime',
        'crown_updated_at' => 'datetime',
    ];
}