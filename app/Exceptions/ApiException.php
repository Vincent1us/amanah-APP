<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

/** Error bisnis yang dirender langsung sebagai JSON dengan HTTP status yang sesuai. */
class ApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly array $meta = [],
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $headers = isset($this->meta['retry_after']) ? ['Retry-After' => (string) $this->meta['retry_after']] : [];
        return ApiResponse::error($this->getMessage(), $this->errorCode, $this->status, $this->errors, $this->meta, $headers);
    }
}
