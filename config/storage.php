<?php declare(strict_types=1);

return [
    'type' => 's3',
    'config' => [
        's3' => [
            'region' => yaml('storage.s3.region'),
            'bucket' => yaml('storage.s3.bucket'),
            'key' => yaml('storage.s3.key'),
            'secret' => yaml('storage.s3.secret'),
            'url_prefix' => yaml('storage.s3.url_prefix'),
        ],
    ],
];
