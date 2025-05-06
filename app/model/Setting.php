<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

/**
 * 配置表
 */
class Setting extends BaseModel
{
    protected $table = 'setting';

    protected $primaryKey = 'name';

    public $incrementing = false;

    public $timestamps = false;
}