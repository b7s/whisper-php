<?php

declare(strict_types=1);

use WhisperPHP\Config;

test('config can be created with default values', function () {
    $config = new Config();

    expect($config->dataDir)->toBeNull()
        ->and($config->binaryPath)->toBeNull()
        ->and($config->modelPath)->toBeNull()
        ->and($config->ffmpegPath)->toBeNull()
        ->and($config->model)->toBe('base')
        ->and($config->language)->toBe('auto');
});

test('config can be created with custom values', function () {
    $config = new Config(
        dataDir: '/custom/path',
        binaryPath: '/custom/binary',
        modelPath: '/custom/model',
        ffmpegPath: '/custom/ffmpeg',
        model: 'large',
        language: 'pt',
    );

    expect($config->dataDir)->toBe('/custom/path')
        ->and($config->binaryPath)->toBe('/custom/binary')
        ->and($config->modelPath)->toBe('/custom/model')
        ->and($config->ffmpegPath)->toBe('/custom/ffmpeg')
        ->and($config->model)->toBe('large')
        ->and($config->language)->toBe('pt');
});
