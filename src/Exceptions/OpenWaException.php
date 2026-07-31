<?php

namespace ZarulIzham\OpenWa\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class OpenWaException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();

        $message = is_array($body)
            ? ($body['message'] ?? $body['error'] ?? "OpenWA API request failed with status {$response->status()}")
            : "OpenWA API request failed with status {$response->status()}";

        if (is_array($message)) {
            $message = implode(', ', $message);
        }

        return new self($message, $response->status(), is_array($body) ? $body : null);
    }

    public static function noReadySession(): self
    {
        return new self('No OpenWA session with status "ready" is available.');
    }
}
