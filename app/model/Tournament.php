<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 赛事表
 * @property int $id
 * @property string $crown_tournament_id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tournament extends BaseModel
{
    protected $table = 'tournament';
}