<?php declare(strict_types=1);

namespace support\attribute;

use Attribute;
use support\trait\ResolveAttribute;

/**
 * 标记接口允许匿名访问
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AllowGuest
{
    use ResolveAttribute;
}