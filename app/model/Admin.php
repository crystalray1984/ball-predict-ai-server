<?php declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 管理端用户
 *
 * @property int $id
 * @property string $username
 * @property string $password
 * @property int $status
 * @property int $is_super
 */
class Admin extends Model
{
    protected $table = 'admin';

    public function jsonSerialize(): array
    {
        $array = parent::toArray();
        unset($array['password']);
        unset($array['deleted_at']);
        return $array;
    }
}