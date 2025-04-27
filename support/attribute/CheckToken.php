<?php declare(strict_types=1);

namespace support\attribute;

use support\trait\ResolveAttribute;

abstract class CheckToken
{
    use ResolveAttribute;

    public string $type;
}