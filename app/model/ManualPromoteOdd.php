<?php declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 手动推荐的盘口
 * @property int $id
 * @property int $record_id 推荐记录id
 * @property int $match_id 比赛id
 * @property string $variety 玩法
 * @property string $period 时段
 * @property string $condition 盘口1
 * @property string $type 投注方向1
 * @property string|null $condition2 盘口2
 * @property string|null $type2 投注方向2
 *
 */
class ManualPromoteOdd extends BaseModel
{
    use SoftDeletes;

    protected $table = 'manual_promote_odd';
}