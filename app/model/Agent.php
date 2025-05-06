<?php declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 代理表
 * @property int $id
 * @property int $parent_id
 * @property string $code
 * @property string $username
 * @property string $password
 * @property int $status
 */
class Agent extends Model
{
    protected $table = 'agent';

    protected static $unguarded = true;

    protected $casts = [
        'created_at' => 'datetime:c',
        'updated_at' => 'datetime:c',
    ];

    protected $hidden = ['password'];
}