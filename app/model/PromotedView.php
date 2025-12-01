<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 推荐记录视图
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
 * @property Carbon $match_time
 * @property string $crown_match_id
 * @property int $tournament_id
 * @property int $team1_id
 * @property int $team2_id
 * @property int $tournament_label_id
 * @property string $tournament_name
 * @property string $team1_name
 * @property string $team2_name
 */
class PromotedView extends BaseModel
{
    protected $table = 'v_promoted';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'match_time' => 'datetime',
        'extra' => 'array',
    ];
}