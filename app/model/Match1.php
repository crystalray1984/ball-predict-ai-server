<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;
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
 * @property int|null $score1
 * @property int|null $score2
 * @property int|null $corner1
 * @property int|null $corner2
 * @property int|null $score1_period1
 * @property int|null $score2_period1
 * @property int|null $corner1_period1
 * @property int|null $corner2_period1
 * @property int $error_status
 */
class Match1 extends BaseModel
{
    protected $table = 'match';

    protected $casts = [
        'match_time' => 'datetime',
    ];
}