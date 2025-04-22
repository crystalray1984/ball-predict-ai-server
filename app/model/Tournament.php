<?php declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 赛事表
 * @property int $id
 * @property int $crown_tournament_id
 * @property string $name
 */
class Tournament extends Model
{
    protected $table = 'tournament';
}