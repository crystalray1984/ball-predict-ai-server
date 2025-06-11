<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * 通知发送记录
 * @property int $id
 * @property string $keyword
 * @property Carbon $created_at
 */
class NotificationLog extends BaseModel
{
    protected $table = 'notification_log';

    const UPDATED_AT = null;
}