<?php

declare(strict_types=1);

namespace App\Exceptions\AI;

use InvalidArgumentException;

/**
 * Thrown when the gateway receives a malformed NarrativeRequest —
 * unknown modelAlias, missing API key when ANTHROPIC_ENABLED=true, etc.
 *
 * This is a programmer error (or a misconfiguration) — distinct from
 * NarrativeGenerationException which signals an API/runtime failure.
 */
final class InvalidNarrativeRequestException extends InvalidArgumentException
{
}
