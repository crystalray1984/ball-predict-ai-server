<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\Model;

/**
 * 比赛表
 * @property int $id
 * @property int $tournament_id
 * @property int $crown_match_id
 * @property int $team1_id
 * @property int $team2_id
 * @property Carbon $match_time
 * @property string $status
 */
class Match1 extends Model
{
    protected $table = 'match';

    protected $casts = [
        'match_time' => 'datetime',
    ];
}