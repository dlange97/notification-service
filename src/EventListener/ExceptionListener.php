<?php

declare(strict_types=1);

namespace App\EventListener;

use MyDashboard\Shared\EventListener\ExceptionListener as SharedExceptionListener;

/**
 * Catches all exceptions and returns appropriate HTTP responses.
 * - 400 for validation errors
 * - 400 for client errors (bad input)
 * - 500 for unexpected errors
 */
readonly class ExceptionListener extends SharedExceptionListener
{
}
