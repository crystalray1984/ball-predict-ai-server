<?php declare(strict_types=1);

namespace support\storage;

class Storage
{
    protected const ENGINES = [
        's3' => S3Engine::class,
    ];

    /**
     * @var array<string, Engine>
     */
    protected static array $instances = [];

    public static function instance(?string $type = null): Engine
    {
        if (empty($type)) {
            $type = config('storage.type');
        }
        if (isset(self::$instances[$type])) {
            return self::$instances[$type];
        }
        if (!isset(self::ENGINES[$type])) {
            throw new \RuntimeException('invalid storage type ' . $type);
        }
        $class = self::ENGINES[$type];
        return (self::$instances[$type] = new $class(config("storage.config.$type", [])));
    }
}