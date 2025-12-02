<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use LaravelWhisper\Config;
use LaravelWhisper\Whisper;
use LaravelWhisper\Translator;

/**
 * laravelwhisper - Example Usage
 * 
 * This file demonstrates all features of the library.
 * Uncomment the examples you want to try and provide a valid audio file path.
 */

// ============================================================================
// SETUP
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║              laravelwhisper - Audio Transcription                ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Set locale (optional)
Translator::setLocale('en');

// Create service with default configuration
$whisper = new Whisper();

// Or with custom configuration
// $config = new Config(
//     model: 'base',           // Model size: tiny, base, small, medium, large
//     language: 'auto',        // Language: auto, en, pt, es, fr, etc.
//     dataDir: '/custom/path'  // Custom directory for binaries and models
// );
// $whisper = new Whisper($config);

echo "📊 Checking Whisper status...\n";
$status = $whisper->getStatus();
echo "   Binary:  " . ($status['binary'] ? '✓' : '✗') . "\n";
echo "   Model:   " . ($status['model'] ? '✓' : '✗') . "\n";
echo "   FFmpeg:  " . ($status['ffmpeg'] ? '✓' : '✗') . "\n";
echo "   GPU:     " . ($status['gpu'] ? '✓ Available' : '✗ Not available') . "\n\n";

// Setup if needed
if (!$status['binary'] || !$status['model'] || !$status['ffmpeg']) {
    echo "⚙️  Setting up Whisper (downloading binaries, model and FFmpeg)...\n";
    echo "   This may take a few minutes on first run...\n\n";

    try {
        $whisper->setup();
        echo "✓ Setup completed successfully!\n\n";
    } catch (\Exception $e) {
        echo "✗ Setup failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (!$whisper->isAvailable()) {
    echo "✗ Whisper is not available. Please run setup first.\n";
    exit(1);
}

echo "✓ Whisper is ready to use!\n\n";

// ============================================================================
// EXAMPLES - Uncomment the ones you want to try
// ============================================================================

// Set your audio file path here
$audioPath = '/path/to/your/audio.mp3';

// Check if file exists before running examples
if (!file_exists($audioPath)) {
    echo "⚠️  Audio file not found: {$audioPath}\n";
    echo "   Please update \$audioPath variable with a valid audio file.\n\n";
    echo "💡 Examples below are commented out. Uncomment them to try!\n";
    exit(0);
}

// ----------------------------------------------------------------------------
// Example 1: Simple Transcription
// ----------------------------------------------------------------------------
echo "═══════════════════════════════════════════════════════════════\n";
echo "Example 1: Simple Transcription\n";
echo "═══════════════════════════════════════════════════════════════\n";

$text = $whisper->audio($audioPath)->text();
echo "📝 Transcription:\n{$text}\n\n";

// ----------------------------------------------------------------------------
// Example 2: Transcription with Timestamps
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 2: Transcription with Timestamps\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $segments = $whisper->audio($audioPath)->segments();
// foreach ($segments as $i => $segment) {
//     echo sprintf(
//         "[%s --> %s] %s\n",
//         $segment['start'],
//         $segment['end'],
//         $segment['text']
//     );
// }
// echo "\n";

// ----------------------------------------------------------------------------
// Example 3: Language Detection
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 3: Language Detection\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $result = $whisper->audio($audioPath)->run();
// $language = $result->detectedLanguage();
// echo "🌍 Detected language: {$language}\n";
// echo "📝 Text: {$result->text()}\n\n";

// ----------------------------------------------------------------------------
// Example 4: Translation to English
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 4: Translation to English\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $translated = $whisper->audio($audioPath)
//     ->toEnglish()
//     ->text();
// echo "🌐 English translation:\n{$translated}\n\n";

// ----------------------------------------------------------------------------
// Example 5: Export to Subtitle Formats
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 5: Export to Subtitle Formats\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// // SRT format (most common for videos)
// $whisper->audio($audioPath)->saveTo('/tmp/output.srt');
// echo "✓ Saved SRT: /tmp/output.srt\n";
//
// // VTT format (web videos)
// $whisper->audio($audioPath)->saveTo('/tmp/output.vtt');
// echo "✓ Saved VTT: /tmp/output.vtt\n";
//
// // JSON format (for APIs)
// $whisper->audio($audioPath)->saveTo('/tmp/output.json');
// echo "✓ Saved JSON: /tmp/output.json\n";
//
// // CSV format (for analysis)
// $whisper->audio($audioPath)->saveTo('/tmp/output.csv');
// echo "✓ Saved CSV: /tmp/output.csv\n\n";

// ----------------------------------------------------------------------------
// Example 6: High-Quality Transcription with Beam Search
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 6: High-Quality Transcription (Beam Search)\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $text = $whisper->audio($audioPath)
//     ->improveDecode(5)  // Higher = better quality, slower
//     ->text();
// echo "📝 High-quality transcription:\n{$text}\n\n";

// ----------------------------------------------------------------------------
// Example 7: Voice Activity Detection (Remove Silence)
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 7: Voice Activity Detection\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $segments = $whisper->audio($audioPath)
//     ->filterNonSpeech(0.5)  // 0.0-1.0 (lower = more sensitive)
//     ->segments();
//
// echo "🎤 Speech segments (silence removed):\n";
// foreach ($segments as $segment) {
//     echo "[{$segment['start']}] {$segment['text']}\n";
// }
// echo "\n";

// ----------------------------------------------------------------------------
// Example 8: Context-Aware Transcription (Initial Prompt)
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 8: Context-Aware Transcription\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// // Medical transcription
// $text = $whisper->audio($audioPath)
//     ->context('Medical terms: hypertension, cardiovascular, diagnosis, prescription')
//     ->text();
// echo "🏥 Medical transcription:\n{$text}\n\n";
//
// // Technical transcription
// $text = $whisper->audio($audioPath)
//     ->context('Technology: API, SDK, Kubernetes, microservices, Docker')
//     ->text();
// echo "💻 Technical transcription:\n{$text}\n\n";

// ----------------------------------------------------------------------------
// Example 9: Speaker Detection (Conversations)
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 9: Speaker Detection (Experimental)\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $segments = $whisper->audio($audioPath)
//     ->detectSpeakers()
//     ->segments();
//
// echo "👥 Conversation with speakers:\n";
// foreach ($segments as $segment) {
//     $speaker = "Speaker " . ($segment['speaker'] ?? 0);
//     echo "[{$speaker}] {$segment['text']}\n";
// }
// echo "\n";

// ----------------------------------------------------------------------------
// Example 10: Progress Tracking
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 10: Progress Tracking\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $text = $whisper->audio($audioPath)
//     ->onProgress(function (int $percent) {
//         echo "\r⏳ Progress: {$percent}%";
//     })
//     ->text();
// echo "\n✓ Completed!\n";
// echo "📝 Result: {$text}\n\n";

// ----------------------------------------------------------------------------
// Example 11: Complete Workflow (All Features Combined)
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 11: Complete Workflow\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $result = $whisper->audio($audioPath)
//     ->improveDecode(5)                    // High quality
//     ->filterNonSpeech(0.5)                   // Remove silence
//     ->detectSpeakers()                 // Identify speakers
//     ->context('Meeting attendees: John, Sarah, Mike')
//     ->onProgress(fn($p) => print("\r⏳ {$p}%"))
//     ->run();
//
// echo "\n";
// echo "🌍 Language: {$result->detectedLanguage()}\n";
// echo "📝 Transcription:\n{$result->text()}\n\n";
// echo "👥 Segments with speakers:\n";
// foreach ($result->segments() as $segment) {
//     $speaker = "Speaker " . ($segment['speaker'] ?? 0);
//     echo "[{$speaker}] {$segment['text']}\n";
// }
//
// // Save in multiple formats
// $result->saveTo('/tmp/meeting.srt');
// $result->saveTo('/tmp/meeting.json');
// echo "\n✓ Saved to /tmp/meeting.srt and /tmp/meeting.json\n\n";

// ----------------------------------------------------------------------------
// Example 12: Batch Processing Multiple Files
// ----------------------------------------------------------------------------
// echo "═══════════════════════════════════════════════════════════════\n";
// echo "Example 12: Batch Processing\n";
// echo "═══════════════════════════════════════════════════════════════\n";
//
// $files = [
//     '/path/to/audio1.mp3',
//     '/path/to/audio2.mp3',
//     '/path/to/audio3.mp3',
// ];
//
// foreach ($files as $i => $file) {
//     if (!file_exists($file)) continue;
//     
//     echo "Processing file " . ($i + 1) . "/" . count($files) . ": " . basename($file) . "\n";
//     
//     $result = $whisper->audio($file)
//         ->filterNonSpeech(0.5)
//         ->onProgress(fn($p) => print("\r  Progress: {$p}%"))
//         ->run();
//     
//     $outputFile = str_replace('.mp3', '.srt', $file);
//     $result->saveTo($outputFile);
//     
//     echo "\n  ✓ Saved to: {$outputFile}\n\n";
// }

echo "═══════════════════════════════════════════════════════════════\n";
echo "✓ Examples completed!\n";
echo "═══════════════════════════════════════════════════════════════\n";
