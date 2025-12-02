<?php

declare(strict_types=1);

use LaravelWhisper\Config;
use LaravelWhisper\WhisperPathResolver;
use LaravelWhisper\WhisperPlatformDetector;

test('path resolver can get data directory', function () {
    $platform = new WhisperPlatformDetector();
    $config = new Config();
    $resolver = new WhisperPathResolver($platform, $config);

    $dataDir = $resolver->getDataDirectory();

    expect($dataDir)->toBeString()->not->toBeEmpty();
});

test('path resolver can get binary path', function () {
    $platform = new WhisperPlatformDetector();
    $config = new Config();
    $resolver = new WhisperPathResolver($platform, $config);

    $binaryPath = $resolver->getBinaryPath();

    expect($binaryPath)->toBeString()->not->toBeEmpty();
});

test('path resolver can get model path', function () {
    $platform = new WhisperPlatformDetector();
    $config = new Config();
    $resolver = new WhisperPathResolver($platform, $config);

    $modelPath = $resolver->getModelPath();

    expect($modelPath)->toBeString()
        ->not->toBeEmpty()
        ->toContain('ggml-');
});

test('path resolver can get ffmpeg path', function () {
    $platform = new WhisperPlatformDetector();
    $config = new Config();
    $resolver = new WhisperPathResolver($platform, $config);

    $ffmpegPath = $resolver->getFfmpegPath();

    expect($ffmpegPath)->toBeString()->not->toBeEmpty();
});

test('path resolver respects custom config paths', function () {
    $platform = new WhisperPlatformDetector();
    $config = new Config(
        dataDir: '/custom/data',
        binaryPath: '/custom/binary',
        modelPath: '/custom/model',
        ffmpegPath: '/custom/ffmpeg'
    );
    $resolver = new WhisperPathResolver($platform, $config);

    expect($resolver->getDataDirectory())->toBe('/custom/data');
});

test('path resolver can generate temp paths', function () {
    $platform = new WhisperPlatformDetector();
    $config = new Config();
    $resolver = new WhisperPathResolver($platform, $config);

    $tempPath = $resolver->getTempPath('test_');

    expect($tempPath)->toBeString()
        ->toContain('test_')
        ->toContain(sys_get_temp_dir());
});

test('path resolver can ensure directories exist', function () {
    $platform = new WhisperPlatformDetector();
    $tempDir = sys_get_temp_dir() . '/laravelwhisper_test_' . uniqid();
    $config = new Config(dataDir: $tempDir);
    $resolver = new WhisperPathResolver($platform, $config);

    $resolver->ensureDirectoriesExist();

    expect($tempDir)->toBeDirectory();

    // Cleanup
    if (is_dir($tempDir)) {
        rmdir($tempDir . '/bin');
        rmdir($tempDir . '/models');
        rmdir($tempDir);
    }
});
