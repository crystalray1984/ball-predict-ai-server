<?php declare(strict_types=1);

namespace app\model;

use support\BaseModel;

class Order extends BaseModel
{
    protected $table = 'order';

    protected $casts = [
        'payment_at' => 'datetime',
    ];
}