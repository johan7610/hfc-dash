<?php

declare(strict_types=1);

namespace App\Exceptions\AI;

use RuntimeException;
use Throwable;

/**
 * Thrown when the Anthropic API call fails AND the caller did not provide
 * fallback data via NarrativeRequest::$fallbackData. Callers that want a
 * "never throw" path must supply fallbackData.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8 (failure modes per surface).
 */
final class NarrativeGenerationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $cacheKey,
        public readonly ?string $upstreamError = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
