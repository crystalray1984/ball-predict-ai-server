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

    public function jsonSerialize(): array
    {
        $array = parent::toArray();
        $array['password'] = '';
        return $array;
    }
}