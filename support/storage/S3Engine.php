<?php declare(strict_types=1);

namespace support\storage;

use Aws\Credentials\Credentials;
use Aws\S3\PostObjectV4;
use Aws\S3\S3Client;

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
        $obj = new PostObjectV4($this->s3Client, $this->bucket, ['key' => $remotePath]);

        return [
            'post_url' => "https://$this->bucket.s3.{$this->config['region']}.amazonaws.com/",
            'fields' => $obj->getFormInputs(),
            'file_path' => $remotePath,
            'file_url' => $this->urlPrefix . $remotePath,
        ];
    }
}