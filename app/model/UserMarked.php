<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

/**
 * 用户标记的推荐数据表
 */
class UserMarked extends BaseModel
{
    protected $table = 'user_marked';

    public $incrementing = false;

    public $timestamps = false;
}