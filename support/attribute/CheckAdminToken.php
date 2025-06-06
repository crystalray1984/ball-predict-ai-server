<?php declare(strict_types=1);

namespace support\attribute;

use Attribute;
use support\trait\ResolveAttribute;

/**
 * 标记接口需要检查管理端token
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CheckAdminToken
{
    use ResolveAttribute;

    public string $type = 'admin';
}