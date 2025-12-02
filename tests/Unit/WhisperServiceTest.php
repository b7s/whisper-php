<?php

declare(strict_types=1);

use LaravelWhisper\Config;
use LaravelWhisper\NullLogger;
use LaravelWhisper\Whisper;

test('whisper service can be instantiated', function () {
    $service = new Whisper();

    expect($service)->toBeInstanceOf(Whisper::class);
});

test('whisper service can be instantiated with custom config', function () {
    $config = new Config(model: 'large');
    $logger = new NullLogger();
    
    $service = new Whisper($config, $logger);

    expect($service)->toBeInstanceOf(Whisper::class);
});

test('whisper service can check availability', function () {
    $service = new Whisper();

    $isAvailable = $service->isAvailable();

    expect($isAvailable)->toBeBool();
});

test('whisper service can check GPU support', function () {
    $service = new Whisper();

    $hasGpu = $service->hasGpuSupport();

    expect($hasGpu)->toBeBool();
});

test('whisper service can get status', function () {
    $service = new Whisper();

    $status = $service->getStatus();

    expect($status)->toBeArray()
        ->toHaveKeys(['binary', 'model', 'ffmpeg', 'gpu'])
        ->and($status['binary'])->toBeBool()
        ->and($status['model'])->toBeBool()
        ->and($status['ffmpeg'])->toBeBool()
        ->and($status['gpu'])->toBeBool();
});
