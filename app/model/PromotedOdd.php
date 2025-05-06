<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;
use support\Model;

/**
 * 推荐盘口表
 * @property int $id
 * @property int $odd_id
 * @property int $crown_match_id
 * @property string $game
 * @property string $base
 * @property string $variety
 * @property string $period
 * @property string $type
 * @property string $condition
 */
class PromotedOdd extends BaseModel
{
    protected $table = 'promoted_odd';

    const UPDATED_AT = null;
}