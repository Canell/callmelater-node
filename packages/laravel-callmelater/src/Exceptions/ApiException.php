<?php

namespace CallMeLater\Laravel\Exceptions;

use Illuminate\Http\Client\Response;

class ApiException extends CallMeLaterException
{
    protected int $statusCode;

    protected array $validationErrors;

    protected string $responseBody;

    public function __construct(
        string $message,
        int $statusCode,
        array $validationErrors = [],
        string $responseBody = ''
    ) {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->validationErrors = $validationErrors;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getErrorBag(): array
    {
        return $this->validationErrors;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public static function fromResponse(Response $response, string $context = 'API request'): self
    {
        $body = $response->body();
        $json = $response->json();
        $message = $json['message'] ?? $body;
        $errors = $json['errors'] ?? [];
        $statusCode = $response->status();
        $fullMessage = "Failed to {$context}: {$message}";

        if ($statusCode === 401) {
            return new AuthenticationException($fullMessage, $statusCode, $errors, $body);
        }

        return new self(
            message: $fullMessage,
            statusCode: $statusCode,
            validationErrors: $errors,
            responseBody: $body,
        );
    }
}
