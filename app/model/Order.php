<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 支付订单表
 *
 * @property int $id
 * @property int $order_date
 * @property string $order_number
 * @property int $user_id
 * @property string $type
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property string $extra
 * @property string $channel_type
 * @property string $channel_id
 * @property string $channel_order_no
 * @property string|null $channel_order_info
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Order extends BaseModel
{
    protected $table = 'order';

    protected $casts = [
        'paid_at' => 'datetime',
    ];
}