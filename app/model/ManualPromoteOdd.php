<?php declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 手动推荐的盘口
 */
class ManualPromoteOdd extends BaseModel
{
    use SoftDeletes;

    protected $table = 'manual_promote_odd';
}