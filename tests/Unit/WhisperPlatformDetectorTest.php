<?php

declare(strict_types=1);

use LaravelWhisper\WhisperPlatformDetector;

test('platform detector can detect OS', function () {
    $detector = new WhisperPlatformDetector();

    $os = $detector->getOS();

    expect($os)->toBeIn(['linux', 'darwin', 'windows']);
});

test('platform detector can detect architecture', function () {
    $detector = new WhisperPlatformDetector();

    $arch = $detector->getArch();

    expect($arch)->toBeString()->not->toBeEmpty();
});

test('platform detector can check if windows', function () {
    $detector = new WhisperPlatformDetector();

    $isWindows = $detector->isWindows();

    expect($isWindows)->toBeBool();
});

test('platform detector can check if macOS', function () {
    $detector = new WhisperPlatformDetector();

    $isMacOS = $detector->isMacOS();

    expect($isMacOS)->toBeBool();
});

test('platform detector can check if linux', function () {
    $detector = new WhisperPlatformDetector();

    $isLinux = $detector->isLinux();

    expect($isLinux)->toBeBool();
});

test('platform detector can check GPU support', function () {
    $detector = new WhisperPlatformDetector();

    $hasGpu = $detector->hasGpuSupport();

    expect($hasGpu)->toBeBool();
});
