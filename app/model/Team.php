<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

/**
 * 队伍表
 * @property int $id
 * @property int $crown_team_id
 * @property string $name
 */
class Team extends BaseModel
{
    protected $table = 'team';
}