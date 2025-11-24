<?php declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\BaseModel;

/**
 * 客户端版本更新包表
 * @property int $id
 * @property int $client_version_id
 * @property int $build_number
 * @property int $is_base
 * @property int $status
 * @property array{
 *     url: string,
 *     hash: string,
 *     size: int
 * }|null $full_info
 * @property array{
 *      url: string,
 *      hash: string,
 *      size: int
 *  }|null $hot_update_info
 * @property array{
 *      url: string,
 *      hash: string,
 *      size: int
 *  }|null $zip_info
 */
class ClientVersionBuild extends BaseModel
{
    use SoftDeletes;

    protected $table = 'client_version_build';

    protected $casts = [
        'full_info' => 'array',
        'hot_update_info' => 'array',
        'zip_info' => 'array'
    ];
}