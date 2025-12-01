<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 推荐记录表
 *
 * @property int $id
 * @property int $match_id
 * @property string $source_type
 * @property int $source_id
 * @property string $channel
 * @property int $is_valid
 * @property string $skip
 * @property int $week_day
 * @property int $week_id
 * @property string $variety
 * @property string $period
 * @property string $type
 * @property string $odd_type
 * @property string $condition
 * @property string|null $value
 * @property int|null $result
 * @property string|null $score
 * @property int|null $score1
 * @property int|null $score2
 * @property array|null $extra
 * @property Carbon $created_at
 */
class Promoted extends BaseModel
{
    protected $table = 'promoted';

    const UPDATED_AT = null;

    protected $casts = [
        'extra' => 'array',
    ];
}