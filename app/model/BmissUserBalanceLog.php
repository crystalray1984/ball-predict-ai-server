<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * Bmiss用户余额变动记录
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $amount
 * @property string $balance_after
 * @property Carbon $created_at
 */
class BmissUserBalanceLog extends BaseModel
{
    protected $table = 'bmiss_user_balance_log';

    const UPDATED_AT = null;
}