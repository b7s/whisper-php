<?php

declare(strict_types=1);

namespace WhisperPHP\Exceptions;

use Exception;

class WhisperException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $details = null,
    ) {
        parent::__construct($message);
    }

    public function getFullMessage(): string
    {
        if ($this->details) {
            return "{$this->message}: {$this->details}";
        }

        return $this->message;
    }
}
