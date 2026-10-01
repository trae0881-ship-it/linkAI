<?php

declare(strict_types=1);

namespace App\Services\Security;

final class UrlSecurityException extends \RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}
