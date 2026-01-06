<?php

declare(strict_types=1);

use WhisperPHP\Logger;
use WhisperPHP\NullLogger;

test('null logger implements logger interface', function () {
    $logger = new NullLogger();

    expect($logger)->toBeInstanceOf(Logger::class);
});

test('null logger info does not throw', function () {
    $logger = new NullLogger();

    $logger->info('Test message', ['key' => 'value']);

    expect(true)->toBeTrue();
});

test('null logger error does not throw', function () {
    $logger = new NullLogger();

    $logger->error('Test error', ['key' => 'value']);

    expect(true)->toBeTrue();
});

test('null logger warning does not throw', function () {
    $logger = new NullLogger();

    $logger->warning('Test warning', ['key' => 'value']);

    expect(true)->toBeTrue();
});

test('custom logger can be implemented', function () {
    $logger = new class implements Logger {
        public array $logs = [];

        public function info(string $message, array $context = []): void
        {
            $this->logs[] = ['level' => 'info', 'message' => $message, 'context' => $context];
        }

        public function error(string $message, array $context = []): void
        {
            $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
        }

        public function warning(string $message, array $context = []): void
        {
            $this->logs[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
        }
    };

    $logger->info('Test info', ['key' => 'value']);
    $logger->error('Test error');
    $logger->warning('Test warning');

    expect($logger->logs)->toHaveCount(3)
        ->and($logger->logs[0]['level'])->toBe('info')
        ->and($logger->logs[1]['level'])->toBe('error')
        ->and($logger->logs[2]['level'])->toBe('warning');
});
