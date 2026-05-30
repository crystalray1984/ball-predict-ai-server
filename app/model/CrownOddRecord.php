<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 皇冠盘口记录表
 *
 * @property int $id
 * @property string $crown_match_id
 * @property string $show_type
 * @property int $is_last
 * @property array $odd_data
 * @property Carbon $created_at
 */
class CrownOddRecord extends BaseModel
{
    protected $table = 'crown_odd_record';

    const UPDATED_AT = null;

    protected $casts = [
        'odd_data' => 'array',
    ];
}