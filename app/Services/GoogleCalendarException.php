<?php

namespace App\Services;

use RuntimeException;

class GoogleCalendarException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct("Google Calendar ({$status}): {$message}");
    }
}
