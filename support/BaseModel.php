<?php declare(strict_types=1);

namespace support;

abstract class BaseModel extends Model
{
    protected $dateFormat = 'Y-m-d\TH:i:s.uZ';
}