<?php

declare(strict_types=1);

use LaravelWhisper\TranscriptionOptions;

test('transcription options can be created with defaults', function () {
    $options = new TranscriptionOptions();

    expect($options->hasTimestamps())->toBeFalse()
        ->and($options->shouldTranslate())->toBeFalse()
        ->and($options->getOutputFormat())->toBeNull()
        ->and($options->getLanguage())->toBeNull()
        ->and($options->getInitialPrompt())->toBeNull()
        ->and($options->shouldUseBeamSearch())->toBeFalse()
        ->and($options->getTemperature())->toBe(0.0)
        ->and($options->isVadEnabled())->toBeFalse()
        ->and($options->shouldDetectSpeakers())->toBeFalse()
        ->and($options->getProgressCallback())->toBeNull();
});

test('transcription options can enable timestamps', function () {
    $options = (new TranscriptionOptions())->withTimestamps();

    expect($options->hasTimestamps())->toBeTrue();
});

test('transcription options can enable translation', function () {
    $options = (new TranscriptionOptions())->toEnglish();

    expect($options->shouldTranslate())->toBeTrue();
});

test('transcription options can set output format', function () {
    $options = (new TranscriptionOptions())->outputFormat('srt');

    expect($options->getOutputFormat())->toBe('srt');
});

test('transcription options throws on invalid format', function () {
    (new TranscriptionOptions())->outputFormat('invalid');
})->throws(InvalidArgumentException::class);

test('transcription options can set language', function () {
    $options = (new TranscriptionOptions())->fromLanguage('pt');

    expect($options->getLanguage())->toBe('pt');
});

test('transcription options can set initial prompt', function () {
    $options = (new TranscriptionOptions())->context('Hello world');

    expect($options->getInitialPrompt())->toBe('Hello world');
});

test('transcription options can enable beam search', function () {
    $options = (new TranscriptionOptions())->improveDecode(10);

    expect($options->shouldUseBeamSearch())->toBeTrue()
        ->and($options->getBeamSize())->toBe(10);
});

test('transcription options beam size minimum is 1', function () {
    $options = (new TranscriptionOptions())->improveDecode(-5);

    expect($options->getBeamSize())->toBe(1);
});

test('transcription options can set temperature', function () {
    $options = (new TranscriptionOptions())->temperature(0.5);

    expect($options->getTemperature())->toBe(0.5);
});

test('transcription options temperature is clamped between 0 and 1', function () {
    $options1 = (new TranscriptionOptions())->temperature(-0.5);
    $options2 = (new TranscriptionOptions())->temperature(1.5);

    expect($options1->getTemperature())->toBe(0.0)
        ->and($options2->getTemperature())->toBe(1.0);
});

test('transcription options can enable vad', function () {
    $options = (new TranscriptionOptions())->filterNonSpeech(0.7);

    expect($options->isVadEnabled())->toBeTrue()
        ->and($options->getVadThreshold())->toBe(0.7);
});

test('transcription options vad threshold is clamped between 0 and 1', function () {
    $options1 = (new TranscriptionOptions())->filterNonSpeech(-0.5);
    $options2 = (new TranscriptionOptions())->filterNonSpeech(1.5);

    expect($options1->getVadThreshold())->toBe(0.0)
        ->and($options2->getVadThreshold())->toBe(1.0);
});

test('transcription options can enable speaker detection', function () {
    $options = (new TranscriptionOptions())->detectSpeakers();

    expect($options->shouldDetectSpeakers())->toBeTrue();
});

test('transcription options can set progress callback', function () {
    $callback = fn (int $progress) => null;
    $options = (new TranscriptionOptions())->onProgress($callback);

    expect($options->getProgressCallback())->toBe($callback);
});

test('transcription options is fluent', function () {
    $options = (new TranscriptionOptions())
        ->withTimestamps()
        ->toEnglish()
        ->fromLanguage('en')
        ->context('test')
        ->improveDecode(5)
        ->temperature(0.5)
        ->filterNonSpeech(0.6)
        ->detectSpeakers();

    expect($options)->toBeInstanceOf(TranscriptionOptions::class)
        ->and($options->hasTimestamps())->toBeTrue()
        ->and($options->shouldTranslate())->toBeTrue()
        ->and($options->getLanguage())->toBe('en')
        ->and($options->getInitialPrompt())->toBe('test')
        ->and($options->shouldUseBeamSearch())->toBeTrue()
        ->and($options->getBeamSize())->toBe(5)
        ->and($options->getTemperature())->toBe(0.5)
        ->and($options->isVadEnabled())->toBeTrue()
        ->and($options->getVadThreshold())->toBe(0.6)
        ->and($options->shouldDetectSpeakers())->toBeTrue();
});
