<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 球探网盘口表
 *
 * @property int $id
 * @property int $match_id
 * @property string $titan007_match_id
 * @property string|null $ah_start
 * @property string|null $ah_end
 * @property string|null $goal_start
 * @property string|null $goal_end
 * @property string|null $ah_period1_start
 * @property string|null $ah_period1_end
 * @property string|null $goal_period1_start
 * @property string|null $goal_period1_end
 * @property string|null $corner_ah_start
 * @property string|null $corner_ah_end
 * @property string|null $corner_goal_start
 * @property string|null $corner_goal_end
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Titan007Odd extends BaseModel
{
    protected $table = 'titan007_odd';
}