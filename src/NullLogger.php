<?php

declare(strict_types=1);

namespace LaravelWhisper;

final class NullLogger implements Logger
{
    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        // No-op
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        // No-op
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        // No-op
    }
}
