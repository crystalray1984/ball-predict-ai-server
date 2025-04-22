<?php declare(strict_types=1);

namespace support;

/**
 * 基于json的响应体
 */
class JsonResponse extends Response
{
    public function getJsonBody(): array
    {
        return $this->jsonBody;
    }

    public function withJsonBody($jsonBody): static
    {
        $this->jsonBody = $jsonBody;
        $this->withBody(json_enc($jsonBody));
        return $this;
    }

    public function __construct(
        protected array $jsonBody,
        int             $status = 200,
        array           $headers = []
    )
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        parent::__construct($status, $headers, json_enc($jsonBody));
    }
}