<?php declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 配置表
 */
class Setting extends Model
{
    protected $table = 'setting';

    protected $primaryKey = 'name';

    public $incrementing = false;

    public $timestamps = false;
}