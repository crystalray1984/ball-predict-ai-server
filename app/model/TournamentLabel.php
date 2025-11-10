<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

/**
 * 联赛标签表
 * @property int $id
 * @property string $luffa_uid
 * @property int $luffa_type
 * @property string $title
 */
class TournamentLabel extends BaseModel
{
    protected $table = 'tournament_label';

    public $timestamps = false;
}