<div align="center">

<img src="docs/laravel-whisper-logo-mini.webp" width="340px" alt="[Laravel Whisper]">


# Laravel Whisper

🎙️ **Transform audio into text with Whisper.cpp AI - directly in PHP!**

A powerful, standalone PHP library that brings state-of-the-art speech recognition to your applications. Built on Whisper.cpp for blazing-fast performance, with zero Laravel dependencies. Transcribe, translate, and analyze audio in 99+ languages with a beautiful fluent API.
</div>

---

✨ **Features:**
- 🚀 Fast local transcription (no API calls, no costs)
- 🌍 99+ languages supported
- 🎯 High accuracy with multiple model sizes
- 💬 Speaker detection and timestamps
- 📝 Export to SRT, VTT, JSON, CSV
- 🔄 Real-time progress tracking
- 🎨 Fluent Laravel-style API
- 🔒 Privacy-first (all processing happens locally)

---

## Requirements

- PHP 8.2 or higher
- Composer 2.x
- curl (for downloads)
- FFmpeg (will be downloaded automatically if not installed)

## Installation

```bash
composer require b7s/laravelwhisper
```

After installation, run the setup command to download Whisper binaries and models:

```bash
php ./vendor/bin/whisper-setup
```

This will download:
- Whisper binary (~50-100 MB)
- Model file (~100-500 MB depending on size)
- FFmpeg binary (~50-80 MB)

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
- Binary installation status
- Model installation status
- FFmpeg availability
- GPU support detection

## Basic Usage

```php
<?php

require 'vendor/autoload.php';

use LaravelWhisper\Whisper;
use LaravelWhisper\Config;

// Create service with default configuration
$whisper = new Whisper();

// Simple transcription
$text = $whisper->audio('/path/to/audio.mp3')->text();
echo $text;
```

## Configuration

The `Config` class accepts the following parameters:

```php
$config = new Config(
    model: 'base.en',
    language: 'auto',
    dataDir: '/custom/path',
    binaryPath: '/path/binary',
    modelPath: '/path/model',
    ffmpegPath: '/path/ffmpeg'
);

$whisper = new Whisper($config);
```

**Parameters:**
- `model`: Model to use (default: `base.en`) - See [Available Models](#available-models) section
- `language`: Language for transcription (default: `auto`) - ISO language code or 'auto' for detection
- `dataDir`: Directory to store binaries and models (default: `~/.local/share/laravelwhisper`)
- `binaryPath`: Custom path to Whisper binary (optional)
- `modelPath`: Custom path to model file (optional)
- `ffmpegPath`: Custom path to FFmpeg binary (optional)


### Model Installation

```bash
# Install default model (base.en)
php ./vendor/bin/whisper-setup

# Install specific model
php ./vendor/bin/whisper-setup --model=small.en
php ./vendor/bin/whisper-setup --model=medium
php ./vendor/bin/whisper-setup --model=large
```

### Performance Tips

1. **Start with `base.en`** - It's the sweet spot for most applications
2. **Use English-only models** when possible - They're faster and more accurate for English (ends with ".en")
3. **Upgrade to `small`** if you need better accuracy
4. **Use `medium` or `large`** only when accuracy is critical (they're much slower)
5. **Use `tiny`** for real-time applications or testing

### Official Documentation

For more details about Whisper models, see:
- [OpenAI Whisper Model Card](https://github.com/openai/whisper/blob/main/model-card.md)
- [Whisper.cpp Models](https://github.com/ggml-org/whisper.cpp/tree/master/models)

## Fluent API

The library provides a fluent API for configuring transcriptions. Chain methods to customize the behavior:

### Basic Transcription

```php
// Get plain text
$text = $whisper->audio('/path/to/audio.mp3')->text();

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
    ->text();
// Output: "Hello, how are you?" (even if spoken in Spanish)
```

**Use cases:**
- Translating foreign language podcasts to English
- Creating English subtitles for international videos
- Processing multilingual customer support calls

### Language Detection

**What it does:** Automatically identifies which language is being spoken in the audio. Returns ISO language codes like 'en', 'pt', 'es', 'fr', etc.

```php
// Detect the spoken language
$language = $whisper->audio('/path/to/audio.mp3')->detectLanguage();
// Returns: 'en' (English), 'pt' (Portuguese), 'es' (Spanish), etc.

// Or get it from the result
$result = $whisper->audio('/path/to/audio.mp3')->run();
echo $result->detectedLanguage(); // 'en'
echo $result->text();
```

**Use cases:**
- Routing calls to appropriate language support teams
- Organizing multilingual audio libraries
- Triggering language-specific processing workflows

### Export Formats (SRT, VTT, JSON, CSV)

**What it does:** Exports transcriptions with timestamps in various formats for different use cases.

```php
// SRT - Standard subtitle format for videos (used by most video players)
$srt = $whisper->audio('/path/to/audio.mp3')->toSrt();

// VTT - Web-based subtitle format (HTML5 video, YouTube)
$vtt = $whisper->audio('/path/to/audio.mp3')->toVtt();

// JSON - Structured data for APIs and web applications
$json = $whisper->audio('/path/to/audio.mp3')->toJson();

// CSV - Spreadsheet format for data analysis
$csv = $whisper->audio('/path/to/audio.mp3')->toCsv();

// Save directly to file (format auto-detected from extension)
$whisper->audio('/path/to/audio.mp3')->saveTo('/path/to/output.srt');
$whisper->audio('/path/to/audio.mp3')->saveTo('/path/to/output.vtt');
```

**Use cases:**
- Creating subtitles for videos (SRT/VTT)
- Building searchable transcription databases (JSON)
- Analyzing speech patterns in spreadsheets (CSV)

### improveDecode - Beam Search (Better Quality)

**What it does:** Uses a more sophisticated decoding algorithm that explores multiple possibilities simultaneously, resulting in more accurate transcriptions at the cost of speed. Higher beam size = better quality but slower.

```php
// Use beam search for better accuracy (slower but more accurate)
$text = $whisper->audio('/path/to/audio.mp3')
    ->improveDecode(5) // beam size: 1-10 (higher = better quality, slower)
    ->text();
```

**When to use:**
- Important transcriptions where accuracy is critical (legal, medical)
- Audio with difficult accents or background noise
- When you have time and need the best possible result

**When NOT to use:**
- Real-time or near-real-time transcription
- Processing large volumes of audio quickly
- Audio quality is already very good

### filterNonSpeech - Voice Activity Detection (VAD)

**What it does:** Automatically detects which parts of the audio contain speech vs silence/noise. This improves segmentation by only transcribing actual speech, ignoring long pauses, background music, or silence.

```php
// Enable VAD for better speech segmentation
$segments = $whisper->audio('/path/to/audio.mp3')
    ->filterNonSpeech(0.5) // threshold 0.0-1.0 (lower = more sensitive)
    ->segments();
```

**Use cases:**
- Podcasts with intro/outro music
- Recordings with long pauses between sentences
- Noisy environments with intermittent speech
- Improving timestamp accuracy

**Threshold guide:**
- `0.3` - Very sensitive (catches quiet speech, may include some noise)
- `0.5` - Balanced (default, good for most cases)
- `0.7` - Conservative (only clear speech, may miss quiet parts)

### Context Guidance

**What it does:** Provides context to the AI model BEFORE transcription starts. This "primes" the model to expect certain words, names, or terminology, dramatically improving accuracy for specialized vocabulary that the model might otherwise mishear.

```php
// Guide transcription with domain-specific context
$text = $whisper->audio('/path/to/medical-audio.mp3')
    ->context('Medical terminology: hypertension, cardiovascular, diagnosis, patient')
    ->text();

// Technical content
$text = $whisper->audio('/path/to/tech-talk.mp3')
    ->context('Technology terms: API, SDK, framework, Kubernetes, microservices')
    ->text();

// Names and brands
$text = $whisper->audio('/path/to/meeting.mp3')
    ->context('Attendees: João Silva, Maria Santos. Company: Acme Corp')
    ->text();
```

**Use cases:**
- Medical transcriptions (drug names, procedures)
- Legal transcriptions (case names, legal terms)
- Technical presentations (software terms, acronyms)
- Business meetings (employee names, product names)
- Any domain with specialized vocabulary

**Tips:**
- Include 5-15 key terms relevant to your audio
- Use actual words/names that will appear in the audio
- Separate terms with commas or natural language
- More specific = better results

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
- Interview transcriptions
- Meeting minutes
- Podcast conversations
- Customer service call analysis
- Multi-speaker presentations

**Limitations:**
- Experimental feature (may not be 100% accurate)
- Doesn't identify who speakers are (just that they're different)
- Works best with clear audio and distinct voices
- May struggle with overlapping speech

### Progress Callback

**What it does:** Allows you to monitor transcription progress in real-time. Useful for long audio files where you want to show a progress bar or status updates to users.

```php
// Monitor transcription progress
$text = $whisper->audio('/path/to/long-audio.mp3')
    ->onProgress(function (int $percent) {
        echo "Progress: {$percent}%\n";
        // Update progress bar, database, websocket, etc.
    })
    ->text();
```

**Use cases:**
- Showing progress bars in web applications
- Updating job status in queues
- Logging progress for long-running tasks
- Providing user feedback during processing

### Chaining Multiple Options

```php
$whisper = new Whisper();

// Simple and direct
echo $whisper->audio('/path/to/audio-1.mp3')->text();

// Combine multiple options
$result = $whisper
    ->audio('/path/to/audio-2.mp3')
    ->fromLanguage('pt')    // Audio language to increase accuracy
    ->toEnglish()           // Translate any language to English
    ->improveDecode(5)      // Improved decoding algorithm (1-10)
    ->filterNonSpeech(0.5)  // Detects speech vs silence/noise (0-1)
    ->detectSpeakers()      // Detects different speakers
    ->context('Add context and technical terms to improve accuracy')
    ->onProgress(fn($p) => echo "{$p}%\n") // Real-time progress
    ->run();

echo $result->detectedLanguage();  // Auto‑detects language
print_r(echo $result->segments()); // Array: text, timestamps, speakers

echo $result->text();   // Pure text
echo $result->toJson(); // or: toCsv, toVtt, toSrt
$result->saveTo('/path/to/output.srt'); // Save directly to file (auto detects format)
```

## Available Models

laravelwhisper supports all Whisper model sizes. Choose based on your accuracy needs and available resources.

### Model Comparison

| Model    | Parameters | Size    | Speed          | Accuracy        | Best For                      |
| -------- | ---------- | ------- | -------------- | --------------- | ----------------------------- |
| `tiny`   | 39M        | ~75 MB  | ⚡⚡⚡⚡⚡ Fastest  | ⭐⭐ Basic        | Real-time, testing            |
| `base`   | 74M        | ~140 MB | ⚡⚡⚡⚡ Very Fast | ⭐⭐⭐ Good        | **Recommended for most uses** |
| `small`  | 244M       | ~460 MB | ⚡⚡⚡ Fast       | ⭐⭐⭐⭐ Very Good  | Production, good quality      |
| `medium` | 769M       | ~1.5 GB | ⚡⚡ Moderate    | ⭐⭐⭐⭐⭐ Excellent | High accuracy needs           |
| `large`  | 1550M      | ~3 GB   | ⚡ Slow         | ⭐⭐⭐⭐⭐ Best      | Maximum accuracy, research    |

### English-Only vs Multilingual

Each model (except `large`) comes in two variants:

- **Multilingual** (`tiny`, `base`, `small`, `medium`): Supports 99+ languages
- **English-only** (`tiny.en`, `base.en`, `small.en`, `medium.en`): Optimized for English, slightly better accuracy

**Recommendation:** Use `.en` models if you only transcribe English audio. They're faster and more accurate for English.

### Which Model Should You Choose?

**For English audio:**
- 🥇 **Best balanced:** `base.en` - Great accuracy, fast, small size
- 🥈 **Higher quality:** `small.en` - Better accuracy, still reasonably fast
- 🥉 **Maximum quality:** `medium.en` or `large` - Best accuracy, slower

**For multilingual audio:**
- 🥇 **Best balanced:** `base` - Good for most languages
- 🥈 **Better quality:** `small` - Recommended for production
- 🥉 **Maximum quality:** `medium` or `large` - Best for critical applications

**For specific use cases:**
- **Real-time transcription:** `tiny` or `tiny.en`
- **Mobile/embedded devices:** `tiny` or `base`
- **Podcasts/interviews:** `base.en` or `small.en`
- **Medical/legal (high accuracy):** `medium.en` or `large`
- **Multiple languages:** `small` or `medium`
- **Testing/development:** `tiny.en` (fastest downloads)

## Real-World Examples

### Example 1: Creating Subtitles for a Video

```php
// Generate accurate subtitles with speaker identification
$whisper->audio('/path/to/interview.mp4')
    ->improveDecode(5)           // High quality
    ->filterNonSpeech(0.5)       // Remove silence
    ->detectSpeakers()           // Identify who's talking
    ->saveTo('subtitles.srt');
```

### Example 2: Transcribing Medical Consultation

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

### Example 3: Multilingual Podcast Processing

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
        ->text();
    echo "English translation: {$english}\n";
}
```

### Example 4: Processing Customer Support Calls

```php
// Transcribe with progress tracking
$segments = $whisper->audio('/path/to/support-call.mp3')
    ->detectSpeakers()
    ->filterNonSpeech(0.5)
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

### Example 5: Batch Processing with Queue

```php
// Process multiple files with different settings
$files = [
    ['path' => 'meeting1.mp3', 'type' => 'meeting'],
    ['path' => 'lecture.mp3', 'type' => 'lecture'],
    ['path' => 'interview.mp3', 'type' => 'interview'],
];

foreach ($files as $file) {
    $transcription = $whisper->audio($file['path']);
    
    // Apply settings based on type
    match($file['type']) {
        'meeting' => $transcription
            ->detectSpeakers()
            ->context('Meeting attendees and topics'),
        'lecture' => $transcription
            ->improveDecode(5)
            ->filterNonSpeech(0.6),
        'interview' => $transcription
            ->detectSpeakers()
            ->improveDecode(5),
    };
    
    $transcription->saveTo(str_replace('.mp3', '.srt', $file['path']));
}
```

### Working with TranscriptionResult

```php
$result = $whisper->audio('/path/to/audio.mp3')
    ->withTimestamps()
    ->run();

// Access different formats
$result->text();              // Plain text
$result->segments();          // Array of segments
$result->detectedLanguage();  // Detected language code
$result->toSrt();             // SRT format string
$result->toVtt();             // VTT format string
$result->toJson();            // JSON format string
$result->toCsv();             // CSV format string
$result->saveTo('file.srt');  // Save to file
```

## Logging

You can implement your own logging class by implementing the `LaravelWhisper\Logger` interface:

```php
<?php

use LaravelWhisper\Logger;

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
composer test
```

## License

MIT

---

<div align="center">

**Made with ❤️ 🐱 by Bruno**

[Report Bug](https://github.com/b7s/laravelwhisper) • [Request Feature](https://github.com/b7s/laravelwhisper/issues)

</div>
