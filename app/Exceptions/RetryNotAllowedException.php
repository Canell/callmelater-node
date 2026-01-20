<?php

namespace App\Exceptions;

use Exception;

class RetryNotAllowedException extends Exception
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
