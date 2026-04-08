<?php

namespace Cubepath;

use Exception;

class APIError extends Exception
{
    private int $statusCode;
    private string $detail;

    public function __construct(int $statusCode, string $detail, string $message = '')
    {
        $this->statusCode = $statusCode;
        $this->detail = $detail;

        $msg = $message ?: "API Error {$statusCode}: {$detail}";
        parent::__construct($msg, $statusCode);
    }

    public static function fromResponse(int $statusCode, string $body): self
    {
        $detail = '';

        $data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['detail'])) {
            $detail = is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
        } else {
            $detail = $body ?: "HTTP {$statusCode}";
        }

        return new self($statusCode, $detail);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function isConflict(): bool
    {
        return $this->statusCode === 409;
    }

    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    public function isBadRequest(): bool
    {
        return $this->statusCode === 400;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }
}
