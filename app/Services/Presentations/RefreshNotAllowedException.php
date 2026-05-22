<?php

declare(strict_types=1);

namespace App\Services\Presentations;

use RuntimeException;

/**
 * Thrown when a refresh request is rejected at the policy layer:
 * link revoked, link already superseded, mode != share, etc.
 */
final class RefreshNotAllowedException extends RuntimeException
{
}
