<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 手动添加的推荐记录表
 *
 * @property int $id
 * @property string $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ManualPromoteRecord extends BaseModel
{
    use SoftDeletes;

    protected $table = 'manual_promote_record';
}