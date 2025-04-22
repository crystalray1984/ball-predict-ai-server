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
 */
class User extends Model
{
    use SoftDeletes;

    protected $table = 'user';

    public function jsonSerialize(): array
    {
        $array = parent::toArray();
        unset($array['password']);
        unset($array['deleted_at']);
        return $array;
    }
}