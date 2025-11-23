<?php declare(strict_types=1);

namespace support\attribute;

use Attribute;
use support\trait\ResolveAttribute;

/**
 * 标记接口需要检查代理端token
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CheckAgentToken
{
    use ResolveAttribute;

    public string $type = 'agent';

    public bool $optional = false;
}