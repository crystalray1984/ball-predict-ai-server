<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

/**
 * 用户客户端配置项
 * @property int $user_id
 * @property array|null $rockball_filter
 */
class UserClientConfig extends BaseModel
{
    protected $table = 'user_client_config';

    public $timestamps = false;

    public $incrementing = false;

    protected $casts = [
        'rockball_filter' => 'array'
    ];
}