<?php declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 手动添加的推荐记录表
 */
class ManualPromoteRecord extends BaseModel
{
    use SoftDeletes;

    protected $table = 'manual_promote_record';
}