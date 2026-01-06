<?php

declare(strict_types=1);

use WhisperPHP\Exceptions\WhisperException;

test('whisper exception can be created with message', function () {
    $exception = new WhisperException('Test error');

    expect($exception->getMessage())->toBe('Test error')
        ->and($exception->details)->toBeNull();
});

test('whisper exception can be created with message and details', function () {
    $exception = new WhisperException('Test error', 'Additional details');

    expect($exception->getMessage())->toBe('Test error')
        ->and($exception->details)->toBe('Additional details');
});

test('whisper exception can get full message', function () {
    $exception = new WhisperException('Test error', 'Additional details');

    expect($exception->getFullMessage())->toBe('Test error: Additional details');
});

test('whisper exception full message without details', function () {
    $exception = new WhisperException('Test error');

    expect($exception->getFullMessage())->toBe('Test error');
});
