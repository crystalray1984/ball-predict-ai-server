<?php declare(strict_types=1);

namespace support\attribute;

use Attribute;

/**
 * 标记接口需要检查代理端token
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CheckAgentToken extends CheckToken
{
    public string $type = 'agent';
}