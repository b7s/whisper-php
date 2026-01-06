<?php

// run this file with: php examples/example-models.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use WhisperPHP\Whisper;

// Create Whisper instance (uses 'base' model by default)
$whisper = new Whisper();

// Check status
$status = $whisper->getStatus();
echo "Whisper Bin: {$status['binary_path']}\n";
echo "Current model: {$status['current_model']}\n";
echo "Available models: " . implode(', ', $status['available_models']) . "\n";
echo "GPU support: " . ($status['gpu'] ? 'Yes' : 'No') . "\n\n";


// Example 1: Use default model
echo "=== Example 1: Using default 'base' (default) model ===\n";
$result = $whisper->audio(__DIR__ . '/audios/example-pt.mp3')
    ->fromLanguage('pt')  // Specify Portuguese for better accuracy
    ->run();

echo "Detected language: " . ($result->detectedLanguage() ?? 'unknown') . "\n";
echo "Text: " . $result->toText() . "\n\n";

// Example 2: Switch to small model for better accuracy
echo "=== Example 2: Switching to 'small' model ===\n";
$whisper->useModel('small');  // Downloads if not available
echo "Current model: " . $whisper->getCurrentModel() . "\n";

$result = $whisper->audio(__DIR__ . '/audios/example-pt.mp3')
    ->fromLanguage('pt')
    ->improveDecode(6)  // Better quality
    ->run();

echo "Text: " . $result->toText() . "\n\n";

// Example 3: Use English-only model for English audio
echo "=== Example 3: Using 'base.en' for English audio ===\n";
$whisper->useModel('base.en');

$result = $whisper->audio(__DIR__ . '/audios/example-en.mp3')
    ->fromLanguage('en')
    ->run();

echo "Text: " . $result->toText() . "\n\n";

// Example 4: Japanese audio with auto-detection
echo "=== Example 4: Japanese audio with auto-detection ===\n";
$whisper->useModel('base');

$result = $whisper->audio(__DIR__ . '/audios/example-jp.mp3')
    ->run();  // No language specified - auto-detect

echo "Detected language: " . ($result->detectedLanguage() ?? 'unknown') . "\n";
echo "Text (Japanese): " . $result->toText() . "\n\n";

// Example 5: Japanese audio translated to English
echo "=== Example 5: Japanese audio translated to English ===\n";
$result = $whisper->audio(__DIR__ . '/audios/example-jp.mp3')
    // Translate to English
    // The translation is not perfect (this is a limitation of the base model).
    ->toEnglish()
    ->run();

echo "Detected language: " . ($result->detectedLanguage() ?? 'unknown') . "\n";
echo "Text (English): " . $result->toText() . "\n\n";

// Example 6: Check and download models
echo "=== Example 6: Managing models ===\n";
if (!$whisper->hasModel('medium')) {
    echo "Downloading 'medium' model...\n";
    $whisper->downloadModel('medium');
}
echo "Available models: " . implode(', ', $whisper->getAvailableModels()) . "\n";

/*
echo "=== Example 7: Extract audio from Video to transcript ===\n";

// Nice CLI with progress bar
// Disables output buffering to display in real time
if (ob_get_level()) {
    ob_end_flush();
}

echo "\n🎬 Transcribing the video...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$startTime = microtime(true);
$lastProgress = -1;

$result = $whisper
    ->useModel('medium')
    ->video(__DIR__ . '/path-to-video/my-video.mp4')
    ->chunk(100 * 1024 * 1024)
    ->timeout(null)  // Unlimited timeout for this large video
    ->fromLanguage('pt')
    ->onProgress(function (int $progress) use (&$lastProgress) {
        // Avoid redrawing if progress hasn't changed
        if ($progress === $lastProgress) {
            return;
        }
        $lastProgress = $progress;

        // Calculate the progress bar.
        $barWidth = 50;
        $completed = (int) round($barWidth * $progress / 100);
        $remaining = $barWidth - $completed;

        // Mount bar
        $bar = str_repeat('█', $completed) . str_repeat('░', $remaining);

        // Clear the line and draw the bar.
        fwrite(STDOUT, "\r📊 Progress: [{$bar}] {$progress}%");

        // Force an immediate flush to display in real time.
        fflush(STDOUT);

        if ($progress === 100) {
            fwrite(STDOUT, " ✓\n");
            fflush(STDOUT);
        }
    })
    ->run();

$duration = round(microtime(true) - $startTime, 2);

$finalText = $result->toText();

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Transcription completed in {$duration}s\n";
echo "🌍 Language detected: " . ($result->detectedLanguage() ?? '(unknow)') . "\n";
echo '📝 Text extracted: ' . mb_substr($finalText, 0, 40, 'UTF-8') . "...\n\n";

// save file
$outputFile = __DIR__ . '/video-transcript.txt';
file_put_contents($outputFile, $finalText);
echo "💾 Saved in: {$outputFile}\n";
*/