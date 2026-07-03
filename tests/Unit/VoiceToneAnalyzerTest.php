<?php

declare(strict_types=1);

use WhisperPHP\VoiceToneAnalyzer;
use WhisperPHP\WhisperPlatformDetector;
use WhisperPHP\WhisperPathResolver;
use WhisperPHP\Config;
use WhisperPHP\NullLogger;

function createTestWav(float $durationSec, float $amplitude): string
{
    $sampleRate = 16000;
    $numSamples = (int) ($durationSec * $sampleRate);
    $dataSize = $numSamples * 2;

    $header = pack('A4VA4', 'RIFF', 36 + $dataSize, 'WAVE');
    $header .= pack('A4V', 'fmt ', 16);
    $header .= pack('vvVVvv', 1, 1, $sampleRate, $sampleRate * 2, 2, 16);
    $header .= pack('A4V', 'data', $dataSize);

    $samples = '';
    $maxVal = (int) round($amplitude * 32767);
    for ($i = 0; $i < $numSamples; $i++) {
        $sample = (int) round(sin(2 * M_PI * 440 * $i / $sampleRate) * $maxVal);
        $samples .= pack('v', $sample & 0xFFFF);
    }

    $path = sys_get_temp_dir() . '/whisper_test_' . uniqid() . '.wav';
    file_put_contents($path, $header . $samples);

    return $path;
}

beforeEach(function () {
    $platform = new WhisperPlatformDetector();
    $paths = new WhisperPathResolver($platform, new Config());
    $logger = new NullLogger();
    $this->analyzer = new VoiceToneAnalyzer($paths, $logger);
});

test('voice tone analyzer returns empty result for no segments', function () {
    $path = createTestWav(1.0, 0.5);
    try {
        $result = $this->analyzer->analyzeWav($path, [], -10.0, -30.0);
        expect($result['has_shouting'])->toBeFalse()
            ->and($result['has_soft_speaking'])->toBeFalse()
            ->and($result['segments'])->toBe([]);
    } finally {
        @unlink($path);
    }
});

test('voice tone analyzer returns empty result for non-existent file', function () {
    $result = $this->analyzer->analyzeWav('/nonexistent.wav', [
        ['start' => '00:00:00.000', 'end' => '00:00:01.000', 'text' => 'test'],
    ], -10.0, -30.0);

    expect($result['has_shouting'])->toBeFalse()
        ->and($result['has_soft_speaking'])->toBeFalse()
        ->and($result['segments'])->toBe([]);
});

test('voice tone analyzer detects shouting segment', function () {
    $path = createTestWav(2.0, 0.8);
    try {
        $segments = [
            ['start' => '00:00:00.000', 'end' => '00:00:01.000', 'text' => 'loud part'],
        ];
        $result = $this->analyzer->analyzeWav($path, $segments, -10.0, -30.0);

        expect($result['has_shouting'])->toBeTrue()
            ->and($result['has_soft_speaking'])->toBeFalse()
            ->and(count($result['shouting']))->toBe(1)
            ->and($result['shouting'][0]['text'])->toBe('loud part')
            ->and($result['shouting'][0]['db'])->toBeGreaterThan(-10.0)
            ->and($result['soft'])->toBe([])
            ->and(count($result['segments']))->toBe(1)
            ->and($result['segments'][0]['tone'])->toBe('shouting');
    } finally {
        @unlink($path);
    }
});

test('voice tone analyzer detects soft segment', function () {
    $path = createTestWav(2.0, 0.005);
    try {
        $segments = [
            ['start' => '00:00:00.000', 'end' => '00:00:01.000', 'text' => 'quiet part'],
        ];
        $result = $this->analyzer->analyzeWav($path, $segments, -10.0, -30.0);

        expect($result['has_shouting'])->toBeFalse()
            ->and($result['has_soft_speaking'])->toBeTrue()
            ->and(count($result['soft']))->toBe(1)
            ->and($result['soft'][0]['text'])->toBe('quiet part')
            ->and($result['soft'][0]['db'])->toBeLessThan(-30.0)
            ->and($result['shouting'])->toBe([])
            ->and(count($result['segments']))->toBe(1)
            ->and($result['segments'][0]['tone'])->toBe('soft');
    } finally {
        @unlink($path);
    }
});

test('voice tone analyzer detects normal segment', function () {
    $path = createTestWav(2.0, 0.05);
    try {
        $segments = [
            ['start' => '00:00:00.000', 'end' => '00:00:01.000', 'text' => 'normal part'],
        ];
        $result = $this->analyzer->analyzeWav($path, $segments, -10.0, -30.0);

        expect($result['has_shouting'])->toBeFalse()
            ->and($result['has_soft_speaking'])->toBeFalse()
            ->and($result['shouting'])->toBe([])
            ->and($result['soft'])->toBe([])
            ->and(count($result['segments']))->toBe(1)
            ->and($result['segments'][0]['tone'])->toBe('normal');
    } finally {
        @unlink($path);
    }
});

test('voice tone analyzer classifies multiple segments correctly', function () {
    $sampleRate = 16000;
    $dataSize = (int) (3.0 * $sampleRate) * 2;

    $header = pack('A4VA4', 'RIFF', 36 + $dataSize, 'WAVE');
    $header .= pack('A4V', 'fmt ', 16);
    $header .= pack('vvVVvv', 1, 1, $sampleRate, $sampleRate * 2, 2, 16);
    $header .= pack('A4V', 'data', $dataSize);

    $samples = '';
    $numSamples = (int) (3.0 * $sampleRate);

    for ($i = 0; $i < $numSamples; $i++) {
        // 0-1s: loud (amplitude 0.8)
        // 1-2s: normal (amplitude 0.05)
        // 2-3s: soft (amplitude 0.005)
        if ($i < $sampleRate) {
            $amp = 0.8;
        } elseif ($i < $sampleRate * 2) {
            $amp = 0.05;
        } else {
            $amp = 0.005;
        }
        $sample = (int) round(sin(2 * M_PI * 440 * $i / $sampleRate) * $amp * 32767);
        $samples .= pack('v', $sample & 0xFFFF);
    }

    $path = sys_get_temp_dir() . '/whisper_test_' . uniqid() . '.wav';
    file_put_contents($path, $header . $samples);

    try {
        $segments = [
            ['start' => '00:00:00.000', 'end' => '00:00:01.000', 'text' => 'loud'],
            ['start' => '00:00:01.000', 'end' => '00:00:02.000', 'text' => 'normal'],
            ['start' => '00:00:02.000', 'end' => '00:00:03.000', 'text' => 'soft'],
        ];
        $result = $this->analyzer->analyzeWav($path, $segments, -10.0, -30.0);

        expect($result['has_shouting'])->toBeTrue()
            ->and($result['has_soft_speaking'])->toBeTrue()
            ->and(count($result['shouting']))->toBe(1)
            ->and($result['shouting'][0]['text'])->toBe('loud')
            ->and(count($result['soft']))->toBe(1)
            ->and($result['soft'][0]['text'])->toBe('soft')
            ->and(count($result['segments']))->toBe(3)
            ->and($result['segments'][0]['tone'])->toBe('shouting')
            ->and($result['segments'][1]['tone'])->toBe('normal')
            ->and($result['segments'][2]['tone'])->toBe('soft');
    } finally {
        @unlink($path);
    }
});

test('voice tone analyzer respects custom thresholds', function () {
    // Amplitude 0.3 sine => rms = 0.3 * 32767 / sqrt(2) ≈ 6950, dBFS ≈ -13.5
    // With shoutThresholdDb = -14.0, this should be classified as shouting
    $path = createTestWav(1.0, 0.3);
    try {
        $segments = [
            ['start' => '00:00:00.000', 'end' => '00:00:01.000', 'text' => 'test'],
        ];
        $result = $this->analyzer->analyzeWav($path, $segments, -14.0, -30.0);

        expect($result['has_shouting'])->toBeTrue();
    } finally {
        @unlink($path);
    }
});

test('voice tone analyzer returns all segment fields', function () {
    $path = createTestWav(1.0, 0.05);
    try {
        $segments = [
            ['start' => '00:00:00.000', 'end' => '00:00:01.000', 'text' => 'hello'],
        ];
        $result = $this->analyzer->analyzeWav($path, $segments, -10.0, -30.0);

        $segment = $result['segments'][0];
        expect($segment)->toHaveKey('start')
            ->and($segment)->toHaveKey('end')
            ->and($segment)->toHaveKey('db')
            ->and($segment)->toHaveKey('tone')
            ->and($segment)->toHaveKey('text')
            ->and($segment['start'])->toBe('00:00:00.000')
            ->and($segment['end'])->toBe('00:00:01.000')
            ->and($segment['text'])->toBe('hello')
            ->and($segment['tone'])->toBe('normal');
    } finally {
        @unlink($path);
    }
});
