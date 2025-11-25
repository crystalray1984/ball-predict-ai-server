<?php declare(strict_types=1);

namespace support\storage;

use Psr\Http\Message\StreamInterface;

/**
 * 存储容器基类
 */
abstract class Engine
{
    /**
     * 计算上传使用的表单
     * @param string $remotePath
     * @return array
     */
    public abstract function getUploadForm(string $remotePath): array;

    /**
     * 检测文件是否存在
     * @param string $remotePath
     * @return bool
     */
    public abstract function exists(string $remotePath): bool;

    /**
     * 获取文件的访问地址
     * @param string $remotePath
     * @return string
     */
    public abstract function getUrl(string $remotePath): string;

    /**
     * 复制文件
     * @param string $fromPath
     * @param string $toPath
     * @return void
     */
    public abstract function copyFile(string $fromPath, string $toPath): void;

    /**
     * 获取文件内容
     * @param string $remotePath 远端文件路径
     * @param string|null $localPath 本地文件路径，如果不传则直接返回原始内容
     * @return string|null
     */
    public abstract function getFile(string $remotePath, ?string $localPath): ?string;

    /**
     * 上传文件
     * @param string $remotePath 远端文件路径
     * @param string|resource|StreamInterface $content 文件内容，可以为文件路径、文件资源或者文件流
     * @param bool $useContentAsPath 如果文件内容为string，是否视为本地文件路径
     * @param array $options 其他配置项
     * @return void
     */
    public abstract function putFile(string $remotePath, mixed $content, bool $useContentAsPath = false, array $options = []): void;

    /**
     * 删除文件
     * @param string $remotePath
     * @return void
     */
    public abstract function deleteFile(string $remotePath): void;
}
