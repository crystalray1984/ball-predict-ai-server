<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 第三方用户连接表
 * @property int $id
 * @property int $user_id
 * @property string $platform
 * @property string $platform_id
 * @property string $account
 * @property string $password
 * @property string $extra
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class UserConnect extends BaseModel
{
    use SoftDeletes;

    protected $table = 'user_connect';
}