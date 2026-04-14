<?php declare(strict_types=1);


namespace app\model;

use Carbon\Carbon;
use support\BaseModel;

/**
 * AI推荐表
 *
 * @property int $id
 * @property int $match_id
 * @property string $crown_match_id
 * @property string $period
 * @property string $odd_type
 * @property string $type
 * @property string $condition
 * @property string|null $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AiPromoted extends BaseModel
{
    protected $table = 'ai_promoted';
}