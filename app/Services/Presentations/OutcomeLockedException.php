<?php

declare(strict_types=1);

namespace App\Services\Presentations;

use RuntimeException;

/**
 * Phase 8 — thrown when an attempt is made to edit a PresentationOutcome
 * that has been locked (90+ days old) for analytics integrity. Controllers
 * catch this and render a 422 with a clear "locked for analytics" message.
 */
final class OutcomeLockedException extends RuntimeException
{
}
