<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

/**
 * 用户表
 * @property int $id
 * @property string $username
 * @property string $password
 * @property int $status
 * @property string $note
 * @property Carbon $expire_time
 * @property int $agent1_id
 * @property int $agent2_id
 */
class User extends Model
{
    use SoftDeletes;

    protected $table = 'user';

    protected static $unguarded = true;

    protected $casts = [
        'expire_time' => 'datetime:c',
        'created_at' => 'datetime:c',
        'updated_at' => 'datetime:c',
        'deleted_at' => 'datetime:c',
    ];

    protected $hidden = ['password', 'deleted_at'];
}