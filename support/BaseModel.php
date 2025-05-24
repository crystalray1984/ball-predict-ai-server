<?php declare(strict_types=1);

namespace support;

use Carbon\Carbon;
use DateTimeInterface;

abstract class BaseModel extends Model
{
    protected static $unguarded = true;

    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::create($date)->toJSON();
    }
}