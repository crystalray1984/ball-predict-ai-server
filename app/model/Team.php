<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 队伍表
 * @property int $id
 * @property string $crown_team_id
 * @property string $titan007_team_id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Team extends BaseModel
{
    protected $table = 'team';
}