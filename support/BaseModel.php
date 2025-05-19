<?php declare(strict_types=1);

namespace support;

abstract class BaseModel extends Model
{
    protected static $unguarded = true;

    protected $dateFormat = 'c';
}