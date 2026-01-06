<?php

declare(strict_types=1);

use WhisperPHP\Whisper;

test('can translate japanese audio to english', function () {
    $whisper = new Whisper();
    
    // Use tiny model for faster CI tests
    if (!$whisper->hasModel('tiny')) {
        $whisper->downloadModel('tiny');
    }
    $whisper->useModel('tiny');
    
    // Skip if whisper is not available
    if (!$whisper->isAvailable()) {
        $this->markTestSkipped('Whisper not available');
    }

    $audioPath = __DIR__ . '/../../examples/audios/example-jp.mp3';
    
    // Skip if audio file doesn't exist
    if (!file_exists($audioPath)) {
        $this->markTestSkipped('Japanese audio file not found');
    }

    $result = $whisper->audio($audioPath)
        ->toEnglish()
        ->run();

    expect($result->toText())
        ->not->toBeEmpty()
        ->and($result->detectedLanguage())
        ->toBe('ja');
})->group('integration', 'translation');

test('can detect and transcribe portuguese audio', function () {
    $whisper = new Whisper();
    
    // Use tiny model for faster CI tests
    if (!$whisper->hasModel('tiny')) {
        $whisper->downloadModel('tiny');
    }
    $whisper->useModel('tiny');
    
    if (!$whisper->isAvailable()) {
        $this->markTestSkipped('Whisper not available');
    }

    $audioPath = __DIR__ . '/../../examples/audios/example-pt.mp3';
    
    if (!file_exists($audioPath)) {
        $this->markTestSkipped('Portuguese audio file not found');
    }

    $result = $whisper->audio($audioPath)
        ->fromLanguage('pt')
        ->run();

    expect($result->toText())
        ->not->toBeEmpty()
        ->and($result->detectedLanguage())
        ->toBe('pt');
})->group('integration', 'transcription');

test('can transcribe english audio', function () {
    $whisper = new Whisper();
    
    // Use tiny.en model for faster CI tests (English-only)
    if (!$whisper->hasModel('tiny.en')) {
        $whisper->downloadModel('tiny.en');
    }
    $whisper->useModel('tiny.en');
    
    if (!$whisper->isAvailable()) {
        $this->markTestSkipped('Whisper not available');
    }

    $audioPath = __DIR__ . '/../../examples/audios/example-en.mp3';
    
    if (!file_exists($audioPath)) {
        $this->markTestSkipped('English audio file not found');
    }

    $result = $whisper->audio($audioPath)
        ->fromLanguage('en')
        ->run();

    expect($result->toText())
        ->not->toBeEmpty()
        ->and($result->detectedLanguage())
        ->toBe('en');
})->group('integration', 'transcription');
