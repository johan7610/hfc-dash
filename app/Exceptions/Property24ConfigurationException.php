<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a property cannot be mapped to a Property24 payload because the
 * owning branch/agency has no P24 agency ID configured. Caught by the
 * syndication service so the user sees a readable error rather than a 500.
 */
class Property24ConfigurationException extends RuntimeException
{
}
