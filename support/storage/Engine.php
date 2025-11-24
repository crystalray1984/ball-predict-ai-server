<?php declare(strict_types=1);

namespace support\storage;

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
}
