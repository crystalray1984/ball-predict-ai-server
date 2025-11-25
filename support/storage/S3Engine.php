<?php declare(strict_types=1);

namespace support\storage;

use Aws\Credentials\Credentials;
use Aws\S3\PostObjectV4;
use Aws\S3\S3Client;
use Psr\Http\Message\StreamInterface;

class S3Engine extends Engine
{
    public readonly S3Client $s3Client;

    public readonly string $bucket;

    public readonly string $urlPrefix;

    public function __construct(protected array $config)
    {
        $this->bucket = $config['bucket'];
        $this->urlPrefix = $config['url_prefix'];
        $this->s3Client = new S3Client([
            'region' => $config['region'],
            'credentials' => new Credentials($config['key'], $config['secret']),
        ]);
    }

    /**
     * 计算上传使用的表单
     * @param string $remotePath
     * @return array
     */
    public function getUploadForm(string $remotePath): array
    {
        $obj = new PostObjectV4(
            $this->s3Client,
            $this->bucket,
            [
                'key' => $remotePath
            ],
            [
                ['key' => $remotePath],
                ['bucket' => $this->bucket],
            ]
        );

        return [
            'post_url' => $this->getRawUrl(''),
            'fields' => $obj->getFormInputs(),
            'file_path' => $remotePath,
            'file_url' => $this->getUrl($remotePath),
        ];
    }

    /**
     * 检测文件是否存在
     * @param string $remotePath
     * @return bool
     */
    public function exists(string $remotePath): bool
    {
        return $this->s3Client->doesObjectExist($this->bucket, $remotePath);
    }

    /**
     * 复制文件
     * @param string $fromPath
     * @param string $toPath
     * @return void
     */
    public function copyFile(string $fromPath, string $toPath): void
    {
        $this->s3Client->copyObject([
            'Bucket' => $this->bucket,
            'Key' => $toPath,
            'CopySource' => "$this->bucket/$fromPath",
        ]);
    }

    /**
     * 删除文件
     * @param string $remotePath
     * @return void
     */
    public function deleteFile(string $remotePath): void
    {
        $this->s3Client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $remotePath,
        ]);
    }

    /**
     * 获取文件的访问地址
     * @param string $remotePath
     * @return string
     */
    public function getUrl(string $remotePath): string
    {
        if (!empty($this->urlPrefix)) {
            return $this->urlPrefix . $remotePath;
        } else {
            return $this->getRawUrl($remotePath);
        }
    }

    /**
     * 获取bucket原始访问地址
     * @param string $remotePath
     * @return string
     */
    protected function getRawUrl(string $remotePath): string
    {
        return "https://$this->bucket.s3.{$this->config['region']}.amazonaws.com/$remotePath";
    }

    /**
     * 获取文件内容
     * @param string $remotePath 远端文件路径
     * @param string|null $localPath 本地文件路径，如果不传则直接返回原始内容
     * @return string|null
     */
    public function getFile(string $remotePath, ?string $localPath): ?string
    {
        $args = [
            'Bucket' => $this->bucket,
            'Key' => $remotePath,
        ];
        if (!empty($localPath)) {
            $args['SaveAs'] = $localPath;
        }

        $resp = $this->s3Client->getObject($args);
        if (empty($localPath)) return null;
        /** @var StreamInterface $body */
        $body = $resp->get('Body');
        return $body->getContents();
    }

    /**
     * 上传文件
     * @param string $remotePath 远端文件路径
     * @param string|resource|StreamInterface $content 文件内容，可以为文件路径、文件资源或者文件流
     * @param bool $useContentAsPath 如果文件内容为string，是否视为本地文件路径
     * @param array $options 其他配置项
     * @return void
     */
    public function putFile(string $remotePath, mixed $content, bool $useContentAsPath = false, array $options = []): void
    {
        $options = [
                'Bucket' => $this->bucket,
                'Key' => $remotePath,
            ] + $options;

        if (is_string($content) && $useContentAsPath) {
            $fp = fopen($content, 'r');
            if (!$fp) {
                throw new \RuntimeException('Unable to open file: ' . $content);
            }
            $options['Body'] = $fp;
        } else {
            $options['Body'] = $content;
        }

        try {
            $this->s3Client->putObject($options);
        } finally {
            if (!empty($fp)) {
                fclose($fp);
            }
        }
    }
}