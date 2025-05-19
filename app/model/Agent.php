<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

/**
 * 代理表
 * @property int $id
 * @property int $parent_id
 * @property string $code
 * @property string $username
 * @property string $password
 * @property int $status
 */
class Agent extends BaseModel
{
    protected $table = 'agent';

    protected $hidden = ['password'];
}