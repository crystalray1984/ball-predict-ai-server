<?php declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 客户端版本表
 * @property int $id
 * @property string $platform
 * @property string $arch
 * @property string $version
 * @property int $version_number
 * @property int $is_mandatory
 * @property int $status
 * @property string|null $note
 */
class ClientVersion extends BaseModel
{
    use SoftDeletes;

    protected $table = 'client_version';
}