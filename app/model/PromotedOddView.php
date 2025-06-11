<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 推荐盘口视图
 * @property int $id
 * @property int $match_id
 * @property int $odd_id
 * @property int $manual_promote_odd_id
 * @property int $is_valid
 * @property string $skip
 * @property string $variety
 * @property string $period
 * @property string $type
 * @property string $condition
 * @property int $back
 * @property string $final_rule
 * @property string|null $type2
 * @property string|null $condition2
 * @property int|null $result
 * @property int|null $result1
 * @property int|null $result2
 * @property string|null $score
 * @property int|null $score1
 * @property int|null $score2
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $match_time
 * @property string $tournament_name
 * @property string $team1_name
 * @property string $team2_name
 */
class PromotedOddView extends BaseModel
{
    protected $table = 'v_promoted_odd';

    protected $casts = [
        'match_time' => 'datetime',
    ];
}