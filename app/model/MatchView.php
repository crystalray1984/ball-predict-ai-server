<?php declare(strict_types=1);

namespace app\model;

/**
 * 比赛信息视图
 * @property string $team1_titan007_id
 * @property string $team1_name
 * @property string $team2_titan007_id
 * @property string $team2_name
 * @property string $tournament_name
 * @property int $tournament_is_open
 */
class MatchView extends Match1
{
    protected $table = 'v_match';

    protected $casts = [
        'match_time' => 'datetime',
    ];
}