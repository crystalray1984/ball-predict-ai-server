<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

class LuffaUser extends BaseModel
{
    protected $table = 'luffa_user';

    protected $primaryKey = 'uid';

    public $incrementing = false;
}