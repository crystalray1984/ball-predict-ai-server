<?php declare(strict_types=1);

namespace support\attribute;

use Attribute;

/**
 * 标记接口需要检查用户端token
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CheckUserToken extends CheckToken
{
    public string $type = 'user';
}