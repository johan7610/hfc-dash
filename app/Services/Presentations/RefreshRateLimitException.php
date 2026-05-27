<?php

declare(strict_types=1);

namespace App\Services\Presentations;

use RuntimeException;

/**
 * Thrown when a refresh request hits the per-link / per-fingerprint rate
 * limit. The public form catches this and shows a friendly "you've already
 * asked — give the agent a moment" page instead of a 500.
 */
final class RefreshRateLimitException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
