<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * Bmiss小程序用户
 * @property int $id
 * @property string $openid
 * @property string $appid
 * @property string $nickname
 * @property string $avatar
 * @property Carbon $created_at
 * @property Carbon $last_login_at
 * @property Carbon $updated_at
 * @property int $profit
 */
class BmissUser extends BaseModel
{
    protected $table = 'bmiss_user';

    protected $casts = [
        'last_login_time' => 'datetime',
    ];
}