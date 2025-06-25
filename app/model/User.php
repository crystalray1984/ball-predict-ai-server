<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 用户表
 * @property int $id
 * @property string $code
 * @property string $nickname
 * @property string $avatar
 * @property int $status
 * @property string $reg_source
 * @property Carbon $expire_time
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property int invite_user_id 邀请人id
 * @property Carbon|null $invited_at 绑定邀请关系的时间
 * @property string $commission 佣金
 */
class User extends BaseModel
{
    use SoftDeletes;

    protected $table = 'user';

    protected $casts = [
        'expire_time' => 'datetime',
        'invited_at' => 'datetime',
    ];

    protected function isExpired(): Attribute
    {
        return new Attribute(
            get: fn(mixed $_value, array $attributes) => Carbon::parse($attributes['expire_time'])->unix() <= time() ? 1 : 0
        );
    }

    protected $appends = ['is_expired'];

    protected $hidden = ['deleted_at'];
}