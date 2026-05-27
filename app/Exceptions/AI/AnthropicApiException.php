<?php

declare(strict_types=1);

namespace App\Exceptions\AI;

use RuntimeException;
use Throwable;

/**
 * Thin wrapper around an Anthropic API error so callers can introspect
 * status code, the raw upstream body, and (in dev) the request URL without
 * digging into Laravel HTTP client internals.
 */
final class AnthropicApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $upstreamBody = null,
        public readonly ?string $requestUrl = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
