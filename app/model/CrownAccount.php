<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 皇冠账号表
 * @property int $id
 * @property string $username
 * @property string $password
 * @property int $status
 * @property string $use_by
 * @property Carbon $use_expires
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CrownAccount extends BaseModel
{
    protected $table = 'crown_account';

    protected $casts = [
        'use_expires' => 'datetime',
    ];
}