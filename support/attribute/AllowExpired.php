<?php declare(strict_types=1);

namespace support\attribute;

use Attribute;
use support\trait\ResolveAttribute;

/**
 * 是否允许已过期的用户调用此接口
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AllowExpired
{
    use ResolveAttribute;
}