<?php
// app/Services/Auth/AuthResult.php

namespace App\Services;

class AuthResult
{
    public bool $success;
    public ?string $message;
    public mixed $data;
    public ?string $error;
    public int $statusCode;

    public function __construct(
        bool $success,
        ?string $message = null,
        mixed $data = null,
        ?string $error = null,
        int $statusCode = 200
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->error = $error;
        $this->statusCode = $statusCode;
    }

    public static function success(string $message, mixed $data = null, int $statusCode = 200): self
    {
        return new self(true, $message, $data, null, $statusCode);
    }

    public static function error(string $message, ?string $error = null, int $statusCode = 400): self
    {
        return new self(false, $message, null, $error, $statusCode);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
