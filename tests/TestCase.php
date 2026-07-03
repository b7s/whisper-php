<?php

declare(strict_types=1);

namespace WhisperPHP\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use WhisperPHP\VoiceToneAnalyzer;
use WhisperPHP\WhisperTranscriber;

/**
 * @property VoiceToneAnalyzer $analyzer
 * @property WhisperTranscriber $transcriber
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
}
