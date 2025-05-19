<?php declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 管理端用户
 *
 * @property int $id
 * @property string $username
 * @property string $password
 * @property int $status
 * @property int $is_super
 */
class Admin extends BaseModel
{
    use SoftDeletes;

    protected $table = 'admin';

    protected $hidden = ['password', 'deleted_at'];
}