<?php

declare(strict_types=1);

use LaravelWhisper\TranscriptionResult;

test('transcription result can be created with text only', function () {
    $result = new TranscriptionResult('Hello world');

    expect($result->toText())->toBe('Hello world')
        ->and($result->segments())->toBe([])
        ->and($result->detectedLanguage())->toBeNull();
});

test('transcription result can be created with segments', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'Hello'],
        ['start' => '00:00:02.000', 'end' => '00:00:04.000', 'text' => 'world'],
    ];

    $result = new TranscriptionResult('Hello world', $segments);

    expect($result->toText())->toBe('Hello world')
        ->and($result->segments())->toBe($segments);
});

test('transcription result can be created with detected language', function () {
    $result = new TranscriptionResult('Hello', [], 'en');

    expect($result->detectedLanguage())->toBe('en');
});

test('transcription result can convert to srt', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'Hello'],
        ['start' => '00:00:02.000', 'end' => '00:00:04.000', 'text' => 'world'],
    ];

    $result = new TranscriptionResult('Hello world', $segments);
    $srt = $result->toSrt();

    expect($srt)->toContain('1')
        ->and($srt)->toContain('00:00:00,000 --> 00:00:02,000')
        ->and($srt)->toContain('Hello')
        ->and($srt)->toContain('2')
        ->and($srt)->toContain('00:00:02,000 --> 00:00:04,000')
        ->and($srt)->toContain('world');
});

test('transcription result can convert to vtt', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'Hello'],
    ];

    $result = new TranscriptionResult('Hello', $segments);
    $vtt = $result->toVtt();

    expect($vtt)->toStartWith('WEBVTT')
        ->and($vtt)->toContain('00:00:00.000 --> 00:00:02.000')
        ->and($vtt)->toContain('Hello');
});

test('transcription result can convert to json', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'Hello'],
    ];

    $result = new TranscriptionResult('Hello', $segments, 'en');
    $json = $result->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['text'])->toBe('Hello')
        ->and($decoded['segments'])->toBe($segments)
        ->and($decoded['language'])->toBe('en');
});

test('transcription result can convert to csv', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'Hello'],
        ['start' => '00:00:02.000', 'end' => '00:00:04.000', 'text' => 'world'],
    ];

    $result = new TranscriptionResult('Hello world', $segments);
    $csv = $result->toCsv();

    expect($csv)->toContain('start,end,text')
        ->and($csv)->toContain('"00:00:00.000","00:00:02.000","Hello"')
        ->and($csv)->toContain('"00:00:02.000","00:00:04.000","world"');
});

test('transcription result csv escapes quotes', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'He said "hello"'],
    ];

    $result = new TranscriptionResult('text', $segments);
    $csv = $result->toCsv();

    expect($csv)->toContain('He said ""hello""');
});

test('transcription result can save to file', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'Hello'],
    ];

    $result = new TranscriptionResult('Hello', $segments);
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.srt';

    $saved = $result->saveTo($tempFile);

    expect($saved)->toBeTrue()
        ->and(file_exists($tempFile))->toBeTrue();

    $content = file_get_contents($tempFile);
    expect($content)->toContain('Hello');

    @unlink($tempFile);
});

test('transcription result detects format from extension', function () {
    $segments = [
        ['start' => '00:00:00.000', 'end' => '00:00:02.000', 'text' => 'Hello'],
    ];

    $result = new TranscriptionResult('Hello', $segments);
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.vtt';

    $result->saveTo($tempFile);
    $content = file_get_contents($tempFile);

    expect($content)->toStartWith('WEBVTT');

    @unlink($tempFile);
});

test('transcription result throws on unsupported format', function () {
    $result = new TranscriptionResult('Hello');
    $result->saveTo('/tmp/test.xyz');
})->throws(InvalidArgumentException::class);

test('transcription result can save txt format', function () {
    $result = new TranscriptionResult('Hello world');
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.txt';

    $result->saveTo($tempFile);
    $content = file_get_contents($tempFile);

    expect($content)->toBe('Hello world');

    @unlink($tempFile);
});
