<?php declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * Luffa游戏表
 *
 * @property int $id
 * @property string $app_id
 * @property string|null $app_entry
 * @property string $img_path
 * @property string $name
 * @property int $is_visible
 * @property int $sort
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LuffaGame extends BaseModel
{
    protected $table = 'luffa_game';
}