<div align="center">

<img src="docs/whisper-php-logo.webp" width="340px" alt="[Whisper PHP logo]">

# Whisper for PHP

🎙️ **Transform audio into text with Whisper.cpp AI - directly in PHP!**

A powerful, standalone PHP library that brings state-of-the-art speech recognition to your applications. Built on Whisper.cpp for blazing-fast performance, with zero Laravel dependencies. Transcribe, translate, and analyze audio in 99+ languages with a beautiful fluent API.

</div>

---

✨ **Features:**

-   🚀 Fast local transcription (no API calls, no costs)
-   🌍 99+ languages supported
-   🎯 High accuracy with multiple model sizes
-   💬 Speaker detection and timestamps
-   📝 Export to SRT, VTT, JSON, CSV
-   🔄 Real-time progress tracking
-   🎨 Fluent Laravel-style API
-   🎬 Video support with automatic audio extraction
-   📦 Smart chunking for large audio/video files
-   🔒 Privacy-first (all processing happens locally)
-   🧪 Real tests ([see here](examples/example-models.php))

---

```php
echo (new Whisper)
    ->audio('/path/to/audio.mp3')
    ->toText();
```

---

## Requirements

-   PHP 8.2 or higher
-   Composer 2.x
-   curl (for downloads)
-   FFmpeg (will be downloaded automatically if not installed)

## Installation

```bash
composer require b7s/whisper-php
```

After installation, run the setup command to download Whisper binaries and models:

```bash
php ./vendor/bin/whisper-setup
```

> Compile for GPU; if that fails, compile for CPU.

This will download:

-   Whisper binary (~50-100 MB)
-   Model file (~100-500 MB depending on size)
-   FFmpeg binary (~50-80 MB)

### Setup Options

```bash
# Use a different model
php ./vendor/bin/whisper-setup --model=small

# Specify language
php ./vendor/bin/whisper-setup --model=large --language=pt

# Custom data directory
php ./vendor/bin/whisper-setup --dir=/custom/path

# Show help
php ./vendor/bin/whisper-setup --help
```

See [Available Models](#available-models): `tiny`, `tiny.en`, `base`, `base.en`, `small`, `small.en`, `medium`, `medium.en`, `large`

### Check Installation Status

```bash
php ./vendor/bin/whisper-status
```

This will show:

-   Binary installation status
-   Model installation status
-   FFmpeg availability
-   GPU support detection (fallback to CPU)

## Basic Usage

```php
<?php

require 'vendor/autoload.php';

use WhisperPHP\Whisper;
use WhisperPHP\Config;

// Create service with default configuration (uses 'base' model)
$whisper = new Whisper();

// Simple transcription
$text = $whisper->audio('/path/to/audio.mp3')->toText();
echo $text;
```

### Chaining Multiple Options

```php
$whisper = new Whisper();

$result = $whisper
    ->audio('/path/to/audio-2.mp3')
    ->fromLanguage('pt')    // Audio language to increase accuracy
    ->toEnglish()           // Translate any language to English
    ->improveDecode(5)      // Improved decoding algorithm (1-10)
    ->filterNonSpeech(0.5)  // Detects speech vs silence/noise (0-1)
    ->detectSpeakers()      // Detects different speakers
    ->context('Add context and technical terms to improve accuracy')
    ->timeout(600)          // Set timeout in seconds (null = unlimited)
    ->onProgress(fn($p) => echo "{$p}%\n") // Real-time progress
    ->run();

echo $result->detectedLanguage();  // Auto‑detects language
print_r($result->segments()); // Array: text, timestamps, speakers

echo $result->toText(); // Pure text
echo $result->toJson(); // or: toJson(true), toCsv, toVtt, toSrt
$result->saveTo('/path/to/output.srt'); // Save directly to file (auto detects format)
```

## Switching Models

You can easily switch between models at runtime. The model will be downloaded automatically if not available:

> **Note:** If you experience errors like "not all tensors loaded from model file", your model file may be corrupted. Delete it from `~/.local/share/whisper-php/models/` and re-download using `php ./vendor/bin/whisper-setup --model=<model-name>`.

```php
$whisper = new Whisper();

// Use a different model (downloads if needed - This may take some time the first time)
$text = $whisper
    ->useModel('small')
    ->audio('/path/to/audio.mp3')
    ->toText();

// Switch to English-only model for better English accuracy
$text = $whisper
    ->useModel('base.en')
    ->audio('/path/to/english-audio.mp3')
    ->toText();

// Use large model for maximum accuracy
$text = $whisper
    ->useModel('large')
    ->audio('/path/to/important-audio.mp3')
    ->toText();

// Check current model
echo $whisper->getCurrentModel(); // 'large'

// Check available (downloaded) models
print_r($whisper->getAvailableModels()); // ['base', 'small', 'base.en', 'large']

// Check if a specific model is downloaded
if (!$whisper->hasModel('medium')) {
    $whisper->downloadModel('medium');
}
```

> See the language tests at [Examples Models](examples/example-models.php).

## Configuration

The `Config` class accepts the following parameters:

```php
$config = new Config(
    model: 'base',
    language: 'auto',
    dataDir: '/custom/path',
    binaryPath: '/path/binary',
    modelPath: '/path/model',
    ffmpegPath: '/path/ffmpeg',
    chunkSize: 10 * 1024 * 1024, // 10 MB
);

$whisper = new Whisper($config);
```

**Parameters:**

-   `model`: Model to use (default: `base`) - See [Available Models](#available-models) section
-   `language`: Language for transcription (default: `auto`) - ISO language code or 'auto' for detection
-   `dataDir`: Directory to store binaries and models (default: `~/.local/share/whisper-php`)
-   `binaryPath`: Custom path to Whisper binary (optional)
-   `modelPath`: Custom path to model file (optional)
-   `ffmpegPath`: Custom path to FFmpeg binary (optional)
-   `chunkSize`: Chunk size for large audio files (default: 20 MB)

### Model Installation

```bash
# Install default model (base - multilingual with auto-detect)
php ./vendor/bin/whisper-setup

# Install specific model
php ./vendor/bin/whisper-setup --model=small.en
php ./vendor/bin/whisper-setup --model=medium
php ./vendor/bin/whisper-setup --model=large
```

### Performance Tips

1. **Start with `base`** - It's the sweet spot for most applications with multilingual support
2. **Use English-only models** when possible - They're faster and more accurate for English (ends with ".en")
3. **Upgrade to `small`** if you need better accuracy
4. **Use `medium` or `large`** only when accuracy is critical (they're much slower)
5. **Use `tiny`** for real-time applications or testing

### Official Documentation

For more details about Whisper models, see:

-   [OpenAI Whisper Model Card](https://github.com/openai/whisper/blob/main/model-card.md)
-   [Whisper.cpp Models](https://github.com/ggml-org/whisper.cpp/tree/master/models)

## Fluent API

The library provides a fluent API for configuring transcriptions. Chain methods to customize the behavior:

### Basic Transcription

```php
// Get plain text
$text = $whisper->audio('/path/to/audio.mp3')->toText();

// Get segments with timestamps
$segments = $whisper->audio('/path/to/audio.mp3')->segments();
// [
//     ['start' => '00:00:00.000', 'end' => '00:00:02.500', 'text' => 'Hello world'],
//     ['start' => '00:00:02.500', 'end' => '00:00:05.000', 'text' => 'How are you?'],
// ]
```

### toEnglish (to English)

**What it does:** Automatically translates speech from any language to English text. Perfect for multilingual content where you need English output regardless of the spoken language.

```php
// Spanish audio → English text
$text = $whisper->audio('/path/to/spanish-audio.mp3')
    ->toEnglish()
    ->toText();
// Output: "Hello, how are you?" (even if spoken in Spanish)
```

**Use cases:**

-   Translating foreign language podcasts to English
-   Creating English subtitles for international videos
-   Processing multilingual customer support calls

### Language Detection and Specification

**Automatic Detection (default):**
By default, Whisper automatically detects the language being spoken. This works best with multilingual models (`base`, `small`, `medium`, `large`).

```php
// Auto-detect language (default behavior)
$result = $whisper->audio('/path/to/audio.mp3')->run();
echo $result->detectedLanguage(); // 'en', 'pt', 'es', 'fr', etc.
echo $result->toText();

// Or use the shorthand
$language = $whisper->audio('/path/to/audio.mp3')->detectLanguage();
```

**Specify Language for Better Accuracy:**
If you know the audio language, specifying it improves accuracy and speed:

```php
// Specify Portuguese for better accuracy
$text = $whisper->audio('/path/to/portuguese-audio.mp3')
    ->fromLanguage('pt')
    ->toText();

// Specify Spanish
$text = $whisper->audio('/path/to/spanish-audio.mp3')
    ->fromLanguage('es')
    ->toText();

// The detected language will match what you specified
$result = $whisper->audio('/path/to/audio.mp3')
    ->fromLanguage('pt')
    ->run();
echo $result->detectedLanguage(); // 'pt'
```

**Use cases:**

-   Routing calls to appropriate language support teams
-   Organizing multilingual audio libraries
-   Triggering language-specific processing workflows
-   Improving transcription accuracy when language is known

### Export Formats (SRT, VTT, JSON, CSV)

**What it does:** Exports transcriptions with timestamps in various formats for different use cases.

```php
// SRT - Standard subtitle format for videos (used by most video players)
$srt = $whisper->audio('/path/to/audio.mp3')->toSrt();

// VTT - Web-based subtitle format (HTML5 video, YouTube)
$vtt = $whisper->audio('/path/to/audio.mp3')->toVtt();

// JSON - Structured data for APIs and web applications
$json = $whisper->audio('/path/to/audio.mp3')->toJson(); // Compact JSON
$json = $whisper->audio('/path/to/audio.mp3')->toJson(true); // Pretty-printed by default

// CSV - Spreadsheet format for data analysis
$csv = $whisper->audio('/path/to/audio.mp3')->toCsv();

// Save directly to file (format auto-detected from extension)
$whisper->audio('/path/to/audio.mp3')->saveTo('/path/to/output.srt');
$whisper->audio('/path/to/audio.mp3')->saveTo('/path/to/output.vtt');
```

**Use cases:**

-   Creating subtitles for videos (SRT/VTT)
-   Building searchable transcription databases (JSON)
-   Analyzing speech patterns in spreadsheets (CSV)

### improveDecode - Beam Search (Better Quality)

**What it does:** Uses a more sophisticated decoding algorithm that explores multiple possibilities simultaneously, resulting in more accurate transcriptions at the cost of speed. Higher beam size = better quality but slower.

```php
// Use beam search for better accuracy (slower but more accurate)
$text = $whisper->audio('/path/to/audio.mp3')
    ->improveDecode(5) // beam size: 1-10 (higher = better quality, slower)
    ->toText();
```

**When to use:**

-   Important transcriptions where accuracy is critical (legal, medical)
-   Audio with difficult accents or background noise
-   When you have time and need the best possible result

**When NOT to use:**

-   Real-time or near-real-time transcription
-   Processing large volumes of audio quickly
-   Audio quality is already very good

### filterNonSpeech - Voice Activity Detection (VAD)

**What it does:** Automatically detects which parts of the audio contain speech vs silence/noise. This improves segmentation by only transcribing actual speech, ignoring long pauses, background music, or silence.

```php
// Enable VAD for better speech segmentation
$segments = $whisper->audio('/path/to/audio.mp3')
    ->filterNonSpeech(0.5) // threshold 0.0-1.0 (lower = more sensitive)
    ->segments();
```

**Use cases:**

-   Podcasts with intro/outro music
-   Recordings with long pauses between sentences
-   Noisy environments with intermittent speech
-   Improving timestamp accuracy

**Threshold guide:**

-   `0.3` - Very sensitive (catches quiet speech, may include some noise)
-   `0.5` - Balanced (default, good for most cases)
-   `0.7` - Conservative (only clear speech, may miss quiet parts)

### Context Guidance

**What it does:** Provides context to the AI model BEFORE transcription starts. This "primes" the model to expect certain words, names, or terminology, dramatically improving accuracy for specialized vocabulary that the model might otherwise mishear.

```php
// Guide transcription with domain-specific context
$text = $whisper->audio('/path/to/medical-audio.mp3')
    ->context('Medical terminology: hypertension, cardiovascular, diagnosis, patient')
    ->toText();

// Technical content
$text = $whisper->audio('/path/to/tech-talk.mp3')
    ->context('Technology terms: API, SDK, framework, Kubernetes, microservices')
    ->toText();

// Names and brands
$text = $whisper->audio('/path/to/meeting.mp3')
    ->context('Attendees: João Silva, Maria Santos. Company: Acme Corp')
    ->toText();
```

**Use cases:**

-   Medical transcriptions (drug names, procedures)
-   Legal transcriptions (case names, legal terms)
-   Technical presentations (software terms, acronyms)
-   Business meetings (employee names, product names)
-   Any domain with specialized vocabulary

**Tips:**

-   Include 5-15 key terms relevant to your audio
-   Use actual words/names that will appear in the audio
-   Separate terms with commas or natural language
-   More specific = better results

### Speaker Detection (Experimental)

**What it does:** Attempts to identify when different people are speaking in a conversation. Each segment gets a speaker ID (0, 1, 2, etc.) indicating who is talking. Note: This doesn't identify WHO the speakers are, just that they're different people.

```php
// Detect speaker changes in conversations
$segments = $whisper->audio('/path/to/conversation.mp3')
    ->detectSpeakers()
    ->segments();
// [
//     ['start' => '00:00:00.000', 'end' => '00:00:02.500', 'text' => 'Hello', 'speaker' => 0],
//     ['start' => '00:00:02.500', 'end' => '00:00:05.000', 'text' => 'Hi there', 'speaker' => 1],
//     ['start' => '00:00:05.000', 'end' => '00:00:08.000', 'text' => 'How are you?', 'speaker' => 0],
// ]
```

**Use cases:**

-   Interview transcriptions
-   Meeting minutes
-   Podcast conversations
-   Customer service call analysis
-   Multi-speaker presentations

**Limitations:**

-   Experimental feature (may not be 100% accurate)
-   Doesn't identify who speakers are (just that they're different)
-   Works best with clear audio and distinct voices
-   May struggle with overlapping speech

### Progress Callback

**What it does:** Allows you to monitor transcription progress in real-time. Useful for long audio files where you want to show a progress bar or status updates to users.

```php
// Monitor transcription progress
$text = $whisper->audio('/path/to/long-audio.mp3')
    ->onProgress(function (int $percent) {
        echo "Progress: {$percent}%\n";
        // Update progress bar, database, websocket, stream, etc.
    })
    ->toText();
```

**Use cases:**

-   Showing progress bars in web applications
-   Updating job status in queues
-   Logging progress for long-running tasks
-   Providing user feedback during processing

### Timeout Configuration

**What it does:** Controls how long the transcription process can run before timing out. Useful for long videos or when you need to prevent processes from hanging indefinitely.

> **Important:** For large video files or long recordings, use `->timeout(null)` to disable the timeout completely. The default 20-minute timeout for videos may not be sufficient for files larger than 500MB or recordings longer than 30 minutes.

```php
// Set custom timeout (in seconds)
$text = $whisper->audio('/path/to/audio.mp3')
    ->timeout(600)  // 10 minutes
    ->toText();

// Disable timeout (unlimited processing time) - RECOMMENDED for large videos
$text = $whisper->audio('/path/to/very-long-audio.mp3')
    ->timeout(null)  // No timeout - unlimited
    ->toText();

// Video files automatically get 20 minutes timeout by default
$text = $whisper->video('/path/to/video.mp4')
    ->toText();  // 20 minutes timeout (auto-applied)

// Override video timeout to unlimited (recommended for large videos)
$text = $whisper->video('/path/to/long-video.mp4')
    ->timeout(null)  // Unlimited - best for large files
    ->toText();

// Or set a custom timeout
$text = $whisper->video('/path/to/video.mp4')
    ->timeout(3600)  // 1 hour
    ->toText();
```

**Default timeouts:**

-   Audio files: 5 minutes (300 seconds)
-   Video files: 20 minutes (1200 seconds)
-   FFmpeg operations: 10 minutes (600 seconds)

**Use cases:**

-   Processing very long recordings (conferences, lectures)
-   Preventing hung processes in production
-   Adjusting for slower systems or large models
-   Queue job timeout management

**Tips:**

-   **Use `timeout(null)` for large videos** - This is the recommended approach for videos over 20 minutes
-   Increase timeout for large models (`medium`, `large`) which process slower
-   Consider chunking for files that consistently timeout
-   The default 20-minute timeout for videos may not be enough for long recordings
-   Audio files default to 5 minutes, which is usually sufficient

### Video Support

**What it does:** Automatically extracts audio from video files using FFmpeg before transcription. Supports all common video formats (MP4, MKV, AVI, MOV, WebM, etc.).

```php
// Transcribe video files directly
$text = $whisper->video('/path/to/video.mp4')
    ->toText();

// Or use audio() - it auto-detects video files
$text = $whisper->audio('/path/to/video.mp4')
    ->toText();

// Generate subtitles from video
$whisper->video('/path/to/movie.mp4')
    ->detectSpeakers()
    ->saveTo('subtitles.srt');

// Check if a file is a video
if ($whisper->isVideoFile('/path/to/file.mp4')) {
    echo "This is a video file";
}
```

**Supported formats:**

-   MP4, MKV, AVI, MOV, WMV, FLV, WebM, M4V
-   MPEG, MPG, 3GP, 3G2, OGV, TS, MTS, M2TS

**Use cases:**

-   Creating subtitles for videos
-   Transcribing video conferences
-   Processing YouTube downloads
-   Extracting dialogue from movies
-   Analyzing video content

### Chunking for Large Files

**What it does:** Automatically splits large audio/video files into smaller chunks for processing. This prevents memory issues and enables processing of very long recordings. Timestamps are automatically adjusted to maintain accuracy across chunks.

```php
// Enable chunking for large files (default: 20MB chunks)
$text = $whisper->audio('/path/to/large-podcast.mp3')
    ->chunk()
    ->toText();

// Custom chunk size (e.g., 10MB)
$text = $whisper->audio('/path/to/huge-file.mp3')
    ->chunk(10 * 1024 * 1024)
    ->toText();

// Chunking is automatically enabled for video files
$text = $whisper->video('/path/to/long-video.mp4')->toText();

// Configure default chunk size globally
$config = new Config(chunkSize: 30 * 1024 * 1024); // 30MB
$whisper = new Whisper($config);
```

**When to use:**

-   Audio/video files larger than 20MB
-   Long recordings (>30 minutes)
-   Processing on memory-constrained systems
-   Video files (automatically enabled)

**How it works:**

1. Calculates optimal chunk duration based on file size
2. Splits audio into overlapping segments
3. Transcribes each chunk independently
4. Merges results with adjusted timestamps
5. Returns seamless transcription

**Use cases:**

-   Multi-hour podcasts or lectures
-   Full-length movies or documentaries
-   All-day conference recordings
-   Large video files
-   Batch processing of media libraries

## Available Models

whisper-php supports all Whisper model sizes. Choose based on your accuracy needs and available resources.

### Model Comparison

| Model    | Parameters | Size    | Speed              | Accuracy             | Best For                      |
| -------- | ---------- | ------- | ------------------ | -------------------- | ----------------------------- |
| `tiny`   | 39M        | ~75 MB  | ⚡⚡⚡⚡⚡ Fastest | ⭐⭐ Basic           | Real-time, testing            |
| `base`   | 74M        | ~140 MB | ⚡⚡⚡⚡ Very Fast | ⭐⭐⭐ Good          | **Recommended for most uses** |
| `small`  | 244M       | ~460 MB | ⚡⚡⚡ Fast        | ⭐⭐⭐⭐ Very Good   | Production, good quality      |
| `medium` | 769M       | ~1.5 GB | ⚡⚡ Moderate      | ⭐⭐⭐⭐⭐ Excellent | High accuracy needs           |
| `large`  | 1550M      | ~3 GB   | ⚡ Slow            | ⭐⭐⭐⭐⭐ Best      | Maximum accuracy, research    |

### English-Only vs Multilingual

Each model (except `large`) comes in two variants:

-   **Multilingual** (`tiny`, `base`, `small`, `medium`): Supports 99+ languages
-   **English-only** (`tiny.en`, `base.en`, `small.en`, `medium.en`): Optimized for English, slightly better accuracy

**Recommendation:** Use `.en` models if you only transcribe English audio. They're faster and more accurate for English.

### Which Model Should You Choose?

**For English audio:**

-   🥇 **Best balanced:** `base.en` - Great accuracy, fast, small size
-   🥈 **Higher quality:** `small.en` - Better accuracy, still reasonably fast
-   🥉 **Maximum quality:** `medium.en` or `large` - Best accuracy, slower

**For multilingual audio:**

-   🥇 **Best balanced:** `base` - Good for most languages
-   🥈 **Better quality:** `small` - Recommended for production
-   🥉 **Maximum quality:** `medium` or `large` - Best for critical applications

**For specific use cases:**

-   **Real-time transcription:** `tiny` or `tiny.en`
-   **Mobile/embedded devices:** `tiny` or `base`
-   **Podcasts/interviews (English):** `base.en` or `small.en`
-   **Podcasts/interviews (multilingual):** `base` or `small`
-   **Medical/legal (high accuracy):** `medium.en` or `large`
-   **Multiple languages with auto-detect:** `base` or `small` (default: `base`)
-   **Testing/development:** `tiny.en` (fastest downloads)

## Real-World Examples

### Example 1: Switching Models for Different Accuracy Needs

```php
$whisper = new Whisper();

// Quick transcription with base model (default)
$quickText = $whisper->audio('/path/to/meeting.mp3')->toText();

// Switch to small model for better accuracy
$whisper->useModel('small');
$betterText = $whisper->audio('/path/to/important-call.mp3')
    ->fromLanguage('pt')  // Specify language for better accuracy
    ->toText();

// Switch to large model for critical transcription
$whisper->useModel('large');
$criticalText = $whisper->audio('/path/to/legal-deposition.mp3')
    ->improveDecode(8)
    ->fromLanguage('en')
    ->toText();

// Check what models you have
print_r($whisper->getAvailableModels()); // ['base', 'small', 'large']
```

### Example 2: Creating Subtitles for a Video

```php
// Generate accurate subtitles with speaker identification
$whisper->video('/path/to/interview.mp4')
    ->improveDecode(5)           // High quality
    ->filterNonSpeech(0.5)       // Remove silence
    ->detectSpeakers()           // Identify who's talking
    ->saveTo('subtitles.srt');

// Process large video file with unlimited timeout (recommended)
$whisper->video('/path/to/long-movie.mp4')
    ->chunk(50 * 1024 * 1024)    // 50MB chunks
    ->timeout(null)              // Unlimited timeout - best for large files
    ->fromLanguage('en')
    ->saveTo('movie-subtitles.srt');
```

### Example 3: Transcribing Medical Consultation

```php
// High-accuracy medical transcription
$result = $whisper->audio('/path/to/consultation.mp3')
            ->improveDecode(8) // Maximum quality
            ->context('Medical terms: hypertension, diabetes, prescription, diagnosis, patient symptoms')
            ->fromLanguage('en')
            ->run();

// Save in multiple formats
$result->saveTo('consultation.txt');
$result->saveTo('consultation.json');
```

### Example 4: Multilingual Podcast Processing

```php
// Detect language and translate to English
$result = $whisper->audio('/path/to/podcast.mp3')
            ->filterNonSpeech(0.6)
            ->run();

$language = $result->detectedLanguage();
echo "Detected language: {$language}\n";

if ($language !== 'en') {
    // Translate to English
    $english = $whisper->audio('/path/to/podcast.mp3')
        ->toEnglish()
        ->toText();
    echo "English translation: {$english}\n";
}
```

### Example 5: Processing Customer Support Calls

```php
// Transcribe with progress tracking and custom timeout
$segments = $whisper->audio('/path/to/support-call.mp3')
    ->detectSpeakers()
    ->filterNonSpeech(0.5)
    ->timeout(900)  // 15 minutes for long calls
    ->context('Company: Acme Corp. Agents: John, Sarah. Products: Pro Plan, Enterprise')
    ->onProgress(function($percent) {
        // Update database or send websocket update
        updateJobProgress($jobId, $percent);
    })
    ->segments();

// Analyze conversation
foreach ($segments as $segment) {
    $speaker = $segment['speaker'] === 0 ? 'Agent' : 'Customer';
    echo "[{$speaker}] {$segment['text']}\n";
}
```

### Example 6: Batch Processing with Queue

```php
// Process multiple files with different settings
$files = [
    ['path' => 'meeting1.mp3', 'type' => 'meeting'],
    ['path' => 'lecture.mp3', 'type' => 'lecture'],
    ['path' => 'interview.mp4', 'type' => 'video'],
];

foreach ($files as $file) {
    // Auto-detect video files
    $transcription = $whisper->isVideoFile($file['path'])
        ? $whisper->video($file['path'])
        : $whisper->audio($file['path']);

    // Apply settings based on type
    match($file['type']) {
        'meeting' => $transcription
            ->detectSpeakers()
            ->context('Meeting attendees and topics'),
        'lecture' => $transcription
            ->improveDecode(5)
            ->filterNonSpeech(0.6),
        'video' => $transcription
            ->chunk()  // Enable chunking for large videos
            ->detectSpeakers()
            ->improveDecode(5),
    };

    $outputPath = preg_replace('/\.(mp3|mp4|mkv|avi)$/', '.srt', $file['path']);
    $transcription->saveTo($outputPath);
}
```

### Working with TranscriptionResult

```php
$result = $whisper->audio('/path/to/audio.mp3')
    ->withTimestamps()
    ->run();

// Access different formats
$result->toText();            // Plain text
$result->segments();          // Array of segments
$result->detectedLanguage();  // Detected language code
$result->toSrt();             // SRT format string
$result->toVtt();             // VTT format string
$result->toJson();            // JSON format string (compact)
$result->toJson(true);        // JSON format string (pretty-printed)
$result->toCsv();             // CSV format string
$result->saveTo('file.srt');  // Save to file
```

## Logging

You can implement your own logging class by implementing the `WhisperPHP\Logger` interface:

```php
<?php

use WhisperPHP\Logger;

class MyLogger implements Logger
{
    public function info(string $message, array $context = []): void
    {
        // Your implementation
    }

    public function error(string $message, array $context = []): void
    {
        // Your implementation
    }

    public function warning(string $message, array $context = []): void
    {
        // Your implementation
    }
}

$whisper = new Whisper(logger: new MyLogger());
```

## Testing

```bash
# Run unit tests only
composer test

# Run all tests (including integration tests with audio files)
./vendor/bin/pest

# Run only integration tests (requires Whisper setup)
./vendor/bin/pest --group=integration

# Run specific test file
./vendor/bin/pest tests/Feature/TranslationTest.php

# Run PHPStan analysis
composer analyse
```

**Note:** Integration tests require Whisper to be set up and will download the `tiny` model (~75MB) for faster execution.

## License

MIT

---

<div align="center">

**Made with ❤️ 🐱 by Bruno**

[Report Bug](https://github.com/b7s/whisper-php) • [Request Feature](https://github.com/b7s/whisper-php/issues)

</div>
