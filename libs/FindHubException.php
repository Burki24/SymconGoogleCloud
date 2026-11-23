<?php

declare(strict_types=1);

namespace SymconGoogleCloud;

use RuntimeException;

final class FindHubException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $retryAt = 0)
    {
        parent::__construct($reason);
    }
}
