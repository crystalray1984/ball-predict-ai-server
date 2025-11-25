<?php declare(strict_types=1);

namespace support\storage;

use Psr\Http\Message\StreamInterface;

/**
 * @method static \array getUploadForm(string $remotePath)
 * @method static \bool exists(string $remotePath)
 * @method static \void copyFile(string $fromPath, string $toPath)
 * @method static \void deleteFile(string $remotePath)
 * @method static \string getUrl(string $remotePath)
 * @method static \mixed getFile(string $remotePath, string|null $localPath)
 * @method static \void putFile(string $remotePath, string|resource|StreamInterface $content, bool $useContentAsPath = false, array $options = [])
 */
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

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::instance()->$name(...$arguments);
    }
}