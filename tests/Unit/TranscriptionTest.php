<?php

declare(strict_types=1);

use LaravelWhisper\Config;
use LaravelWhisper\NullLogger;
use LaravelWhisper\Transcription;
use LaravelWhisper\WhisperPlatformDetector;
use LaravelWhisper\WhisperPathResolver;
use LaravelWhisper\WhisperTranscriber;

beforeEach(function () {
    $platform = new WhisperPlatformDetector();
    $paths = new WhisperPathResolver($platform, new Config());
    $logger = new NullLogger();
    $this->transcriber = new WhisperTranscriber($platform, $paths, $logger);
});

test('transcription can be created', function () {
    $transcription = new Transcription($this->transcriber, new NullLogger());

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription can set audio file', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3');

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with timestamps', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->withTimestamps();

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with translate', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->toEnglish();

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with language', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->fromLanguage('pt');

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with prompt', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->context('Hello world');

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with beam search', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->improveDecode(5);

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with temperature', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->temperature(0.5);

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with vad', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->filterNonSpeech(0.6);

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with speaker detection', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->detectSpeakers();

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription is fluent with progress callback', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->onProgress(fn (int $p) => null);

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription can chain multiple options', function () {
    $transcription = (new Transcription($this->transcriber, new NullLogger()))
        ->file('/path/to/audio.mp3')
        ->withTimestamps()
        ->toEnglish()
        ->fromLanguage('pt')
        ->context('test')
        ->improveDecode(5)
        ->temperature(0.5)
        ->filterNonSpeech(0.6)
        ->detectSpeakers()
        ->onProgress(fn (int $p) => null);

    expect($transcription)->toBeInstanceOf(Transcription::class);
});

test('transcription throws when no file is specified', function () {
    $transcription = new Transcription($this->transcriber, new NullLogger());
    $transcription->run();
})->throws(LaravelWhisper\Exceptions\WhisperException::class, 'No audio file specified');
