<?php

declare(strict_types=1);

namespace LaravelWhisper;

use LaravelWhisper\Exceptions\WhisperException;
use Symfony\Component\Process\Process;

final class WhisperTranscriber
{
    /** @var array<string> Video extensions supported by ffmpeg */
    private const VIDEO_EXTENSIONS = [
        'mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v',
        'mpeg', 'mpg', '3gp', '3g2', 'ogv', 'ts', 'mts', 'm2ts',
    ];

    private int $defaultChunkSize;

    public function __construct(
        private readonly WhisperPlatformDetector $platform,
        private readonly WhisperPathResolver $paths,
        private readonly Logger $logger,
        ?int $defaultChunkSize = null,
    ) {
        $this->defaultChunkSize = $defaultChunkSize ?? Config::DEFAULT_CHUNK_SIZE;
    }

    public function setDefaultChunkSize(int $size): void
    {
        $this->defaultChunkSize = $size;
    }

    /**
     * @return ($withTimestamps is true ? array<int, array{start: string, end: string, text: string}> : string)
     * @throws WhisperException
     */
    public function transcribeFromPath(string $audioPath, bool $withTimestamps = false): string|array
    {
        $options = new TranscriptionOptions();
        if ($withTimestamps) {
            $options->withTimestamps();
        }
        return $this->transcribe($audioPath, $options)->toText();
    }

    /**
     * @return array<int, array{start: string, end: string, text: string}>
     * @throws WhisperException
     */
    public function transcribeWithTimestamps(string $audioPath): array
    {
        $options = (new TranscriptionOptions())->withTimestamps();
        return $this->transcribe($audioPath, $options)->segments();
    }

    /**
     * @throws WhisperException
     */
    public function transcribe(string $audioPath, TranscriptionOptions $options): TranscriptionResult
    {
        if (! $this->isAvailable()) {
            $this->logger->warning('Whisper not available, returning empty transcription');
            return new TranscriptionResult('', []);
        }

        $isVideo = $this->isVideoFile($audioPath);
        $shouldChunk = $options->isChunkingEnabled() || $isVideo;

        if ($shouldChunk) {
            return $this->transcribeWithChunking($audioPath, $options, $isVideo);
        }

        $tempWavPath = $this->convertFileToWav($audioPath);

        try {
            return $this->runWhisper($tempWavPath, $options);
        } finally {
            @unlink($tempWavPath);
        }
    }

    /**
     * Check if the file is a video based on extension.
     */
    public function isVideoFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return \in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    /**
     * Transcribe with chunking support for large files.
     *
     * @throws WhisperException
     */
    private function transcribeWithChunking(string $inputPath, TranscriptionOptions $options, bool $isVideo): TranscriptionResult
    {
        $chunkSize = $options->getChunkSize() ?? $this->defaultChunkSize;

        // Extract audio if video
        if ($isVideo) {
            $this->logger->info('Extracting audio from video file', ['path' => $inputPath]);
            $audioPath = $this->extractAudioFromVideo($inputPath);
        } else {
            $audioPath = $inputPath;
        }

        try {
            $fileSize = filesize($audioPath);
            
            // If file is smaller than chunk size, process normally
            if ($fileSize !== false && $fileSize <= $chunkSize) {
                $this->logger->info('File size within chunk limit, processing without splitting', [
                    'size' => $fileSize,
                    'chunk_size' => $chunkSize,
                ]);
                $tempWavPath = $this->convertFileToWav($audioPath);
                try {
                    return $this->runWhisper($tempWavPath, $options);
                } finally {
                    @unlink($tempWavPath);
                }
            }

            return $this->processInChunks($audioPath, $options, $chunkSize);
        } finally {
            if ($isVideo && isset($audioPath) && $audioPath !== $inputPath) {
                @unlink($audioPath);
            }
        }
    }

    /**
     * Extract audio track from video file.
     *
     * @throws WhisperException
     */
    private function extractAudioFromVideo(string $videoPath): string
    {
        $tempAudioPath = $this->paths->getTempPath('video_audio_extract_') . '.mp3';
        $ffmpegPath = $this->paths->getFfmpegPath();

        $process = new Process([
            $ffmpegPath,
            '-i', $videoPath,
            '-vn',              // No video
            '-acodec', 'libmp3lame',
            '-ab', '128k',
            '-ar', '16000',
            '-ac', '1',
            '-y',
            $tempAudioPath,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->logger->error('Failed to extract audio from video', [
                'error' => $process->getErrorOutput(),
            ]);
            throw new WhisperException('Failed to extract audio from video file');
        }

        return $tempAudioPath;
    }

    /**
     * Process audio file in chunks.
     *
     * @throws WhisperException
     */
    private function processInChunks(string $audioPath, TranscriptionOptions $options, int $chunkSize): TranscriptionResult
    {
        $duration = $this->getAudioDuration($audioPath);
        if ($duration === null) {
            throw new WhisperException('Could not determine audio duration for chunking');
        }

        $fileSize = filesize($audioPath);
        if ($fileSize === false) {
            throw new WhisperException('Could not determine file size');
        }

        // Calculate chunk duration based on file size ratio
        $bytesPerSecond = $fileSize / $duration;
        $chunkDuration = (int) floor($chunkSize / $bytesPerSecond);
        $chunkDuration = max(30, min($chunkDuration, 600)); // Between 30s and 10min

        $this->logger->info('Processing audio in chunks', [
            'total_duration' => $duration,
            'chunk_duration' => $chunkDuration,
            'file_size' => $fileSize,
            'chunk_size' => $chunkSize,
        ]);

        $allSegments = [];
        $allText = [];
        $detectedLanguage = null;
        $currentOffset = 0.0;
        $chunkIndex = 0;

        while ($currentOffset < $duration) {
            $chunkPath = $this->extractChunk($audioPath, $currentOffset, $chunkDuration, $chunkIndex);

            try {
                $tempWavPath = $this->convertFileToWav($chunkPath);

                try {
                    $result = $this->runWhisper($tempWavPath, $options);

                    if ($detectedLanguage === null) {
                        $detectedLanguage = $result->detectedLanguage();
                    }

                    $allText[] = $result->toText();

                    // Adjust segment timestamps with offset
                    foreach ($result->segments() as $segment) {
                        $allSegments[] = [
                            'start' => $this->addTimeOffset($segment['start'], $currentOffset),
                            'end' => $this->addTimeOffset($segment['end'], $currentOffset),
                            'text' => $segment['text'],
                            'speaker' => $segment['speaker'] ?? null,
                        ];
                    }
                } finally {
                    @unlink($tempWavPath);
                }
            } finally {
                @unlink($chunkPath);
            }

            $currentOffset += $chunkDuration;
            $chunkIndex++;
        }

        // Remove null speaker entries if not detecting speakers
        $allSegments = array_map(function ($segment) {
            if ($segment['speaker'] === null) {
                unset($segment['speaker']);
            }
            return $segment;
        }, $allSegments);

        return new TranscriptionResult(
            implode(' ', $allText),
            $allSegments,
            $detectedLanguage
        );
    }

    /**
     * Get audio duration in seconds using ffprobe.
     */
    private function getAudioDuration(string $audioPath): ?float
    {
        $ffmpegPath = $this->paths->getFfmpegPath();
        $ffprobePath = str_replace('ffmpeg', 'ffprobe', $ffmpegPath);

        // Try ffprobe first
        if (file_exists($ffprobePath) || $this->commandExists('ffprobe')) {
            $probePath = file_exists($ffprobePath) ? $ffprobePath : 'ffprobe';
            $process = new Process([
                $probePath,
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $audioPath,
            ]);
            $process->run();

            if ($process->isSuccessful()) {
                $duration = trim($process->getOutput());
                if (is_numeric($duration)) {
                    return (float) $duration;
                }
            }
        }

        // Fallback: use ffmpeg to get duration
        $process = new Process([
            $ffmpegPath,
            '-i', $audioPath,
            '-f', 'null',
            '-',
        ]);
        $process->run();

        $output = $process->getErrorOutput();
        if (preg_match('/Duration:\s*(\d{2}):(\d{2}):(\d{2})\.(\d+)/', $output, $matches)) {
            return (int) $matches[1] * 3600 + (int) $matches[2] * 60 + (int) $matches[3] + (int) $matches[4] / 100;
        }

        return null;
    }

    /**
     * Check if a command exists in PATH.
     */
    private function commandExists(string $command): bool
    {
        $which = $this->platform->isWindows() ? 'where' : 'which';
        $process = new Process([$which, $command]);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Extract a chunk from audio file.
     *
     * @throws WhisperException
     */
    private function extractChunk(string $audioPath, float $startTime, int $duration, int $chunkIndex): string
    {
        $chunkPath = $this->paths->getTempPath("audio_chunk_{$chunkIndex}_") . '.mp3';
        $ffmpegPath = $this->paths->getFfmpegPath();

        $process = new Process([
            $ffmpegPath,
            '-i', $audioPath,
            '-ss', (string) $startTime,
            '-t', (string) $duration,
            '-acodec', 'libmp3lame',
            '-ab', '128k',
            '-y',
            $chunkPath,
        ]);
        $process->setTimeout(150);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->logger->error('Failed to extract audio chunk', [
                'chunk' => $chunkIndex,
                'start' => $startTime,
                'error' => $process->getErrorOutput(),
            ]);
            throw new WhisperException("Failed to extract audio chunk {$chunkIndex}");
        }

        return $chunkPath;
    }

    /**
     * Add time offset to timestamp string.
     */
    private function addTimeOffset(string $timestamp, float $offsetSeconds): string
    {
        // Parse timestamp format: HH:MM:SS.mmm
        if (preg_match('/(\d{2}):(\d{2}):(\d{2})\.(\d{3})/', $timestamp, $matches)) {
            $totalMs = (int) $matches[1] * 3600000 +
                       (int) $matches[2] * 60000 +
                       (int) $matches[3] * 1000 +
                       (int) $matches[4];

            $totalMs += (int) ($offsetSeconds * 1000);

            $hours = (int) floor($totalMs / 3600000);
            $totalMs %= 3600000;
            $minutes = (int) floor($totalMs / 60000);
            $totalMs %= 60000;
            $seconds = (int) floor($totalMs / 1000);
            $ms = $totalMs % 1000;

            return \sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $ms);
        }

        return $timestamp;
    }

    public function isAvailable(): bool
    {
        return file_exists($this->paths->getBinaryPath())
            && file_exists($this->paths->getModelPath())
            && $this->hasRequiredLibraries();
    }

    private function hasRequiredLibraries(): bool
    {
        if ($this->platform->isWindows()) {
            return true;
        }

        $binaryPath = $this->paths->getBinaryPath();
        $env = $this->getWhisperEnvironment();

        $process = new Process([$binaryPath, '--help']);
        $process->setTimeout(5);
        foreach ($env as $key => $value) {
            $process->setEnv([$key => $value]);
        }
        $process->run();

        if ($process->getExitCode() === 127) {
            return false;
        }

        if (str_contains($process->getErrorOutput(), 'cannot open shared object file')) {
            return false;
        }

        return true;
    }

    /**
     * Convert audio file to WAV format optimized for Whisper.
     * Uses optimized settings: 16kHz sample rate, mono channel, 16-bit PCM.
     *
     * @throws WhisperException
     */
    private function convertFileToWav(string $inputPath): string
    {
        $tempWavPath = $this->paths->getTempPath('audio_laravel_whisper_wav_') . '.wav';
        $ffmpegPath = $this->paths->getFfmpegPath();

        $args = [
            $ffmpegPath,
            '-i', $inputPath,
            '-ar', '16000',      // 16kHz sample rate (Whisper requirement)
            '-ac', '1',          // Mono channel (reduces size by ~50%)
            '-c:a', 'pcm_s16le', // 16-bit PCM (lossless, Whisper native format)
        ];

        // Add audio normalization for better transcription quality
        // This helps with quiet or inconsistent audio levels
        $args[] = '-af';
        $args[] = 'loudnorm=I=-16:TP=-1.5:LRA=11';

        $args[] = '-y';
        $args[] = $tempWavPath;

        $process = new Process($args);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->logger->error('Failed to convert audio file', [
                'error' => $process->getErrorOutput(),
            ]);
            throw new WhisperException('Failed to convert audio file');
        }

        return $tempWavPath;
    }

    /**
     * @throws WhisperException
     */
    private function runWhisper(string $wavPath, TranscriptionOptions $options): TranscriptionResult
    {
        $binaryPath = $this->paths->getBinaryPath();
        $modelPath = $this->paths->getModelPath();

        if (! file_exists($binaryPath)) {
            $this->logger->error('Whisper binary not found', ['path' => $binaryPath]);
            return new TranscriptionResult('', []);
        }

        if (! file_exists($modelPath)) {
            $this->logger->error('Whisper model not found', ['path' => $modelPath]);
            return new TranscriptionResult('', []);
        }

        if (! file_exists($wavPath)) {
            $this->logger->error('Audio file not found', ['path' => $wavPath]);
            return new TranscriptionResult('', []);
        }

        $args = $this->buildWhisperArgs($binaryPath, $modelPath, $wavPath, $options);

        $this->logger->info('Running Whisper transcription', [
            'binary' => $binaryPath,
            'model' => $modelPath,
            'audio' => $wavPath,
            'gpu' => $this->platform->hasGpuSupport(),
            'options' => $this->getOptionsForLog($options),
        ]);

        $env = $this->getWhisperEnvironment();
        $process = new Process($args);
        $process->setTimeout(300);
        foreach ($env as $key => $value) {
            $process->setEnv([$key => $value]);
        }

        $progressCallback = $options->getProgressCallback();
        if ($progressCallback !== null) {
            $process->run(function ($type, $buffer) use ($progressCallback) {
                if ($type === Process::ERR && preg_match('/progress\s*=\s*(\d+)/', $buffer, $matches)) {
                    $progressCallback((int) $matches[1]);
                }
            });
        } else {
            $process->run();
        }

        if (! $process->isSuccessful() && $this->platform->hasGpuSupport()) {
            $this->logger->info('GPU transcription failed, falling back to CPU');
            $args[] = '-ng'; // Force CPU mode
            $process = new Process($args);
            $process->setTimeout(300);
            foreach ($env as $key => $value) {
                $process->setEnv([$key => $value]);
            }
            $process->run();
        }

        if (! $process->isSuccessful()) {
            $this->logger->error('Whisper transcription failed', [
                'exit_code' => $process->getExitCode(),
                'error_output' => $process->getErrorOutput(),
                'standard_output' => $process->getOutput(),
                'command' => implode(' ', $args),
            ]);

            return new TranscriptionResult('', []);
        }

        $output = trim($process->getOutput());
        $errorOutput = $process->getErrorOutput();
        $detectedLanguage = $this->extractDetectedLanguage($errorOutput, $options->getLanguage());

        $this->logger->info('Whisper transcription completed', [
            'length' => \strlen($output),
            'preview' => substr($output, 0, 100),
            'detected_language' => $detectedLanguage,
            'stderr_preview' => substr($errorOutput, 0, 500),
        ]);

        return $this->buildResult($output, $options, $detectedLanguage);
    }

    /**
     * @return array<int, string>
     */
    private function buildWhisperArgs(
        string $binaryPath,
        string $modelPath,
        string $wavPath,
        TranscriptionOptions $options
    ): array {
        $args = [
            $binaryPath,
            '-m', $modelPath,
            '-f', $wavPath,
            '-l', $options->getLanguage() ?? 'auto',
            '--print-progress',
        ];

        // Timestamps
        if (! $options->hasTimestamps()) {
            $args[] = '-nt';
            $args[] = '--no-timestamps';
        }

        // Translation
        if ($options->shouldTranslate()) {
            $args[] = '--translate';
        }

        // Initial prompt
        if ($options->getInitialPrompt() !== null) {
            $args[] = '--prompt';
            $args[] = $options->getInitialPrompt();
        }

        // Beam search
        if ($options->shouldUseBeamSearch()) {
            $args[] = '-bs';
            $args[] = (string) $options->getBeamSize();
        }

        // Temperature
        if ($options->getTemperature() > 0.0) {
            $args[] = '--temperature';
            $args[] = (string) $options->getTemperature();
        }

        // VAD (Voice Activity Detection)
        if ($options->isVadEnabled()) {
            $args[] = '--vad';
            $args[] = '--vad-threshold';
            $args[] = (string) $options->getVadThreshold();
        }

        // Speaker diarization (tdrz)
        if ($options->shouldDetectSpeakers()) {
            $args[] = '--tdrz';
        }

        // GPU support: only disable GPU (-ng) if GPU is NOT available
        if (! $this->platform->hasGpuSupport()) {
            $args[] = '-ng';
        }

        return $args;
    }

    /**
     * @return array<string, mixed>
     */
    private function getOptionsForLog(TranscriptionOptions $options): array
    {
        return [
            'timestamps' => $options->hasTimestamps(),
            'translate' => $options->shouldTranslate(),
            'language' => $options->getLanguage(),
            'beam_search' => $options->shouldUseBeamSearch(),
            'vad' => $options->isVadEnabled(),
            'detect_speakers' => $options->shouldDetectSpeakers(),
        ];
    }

    private function extractDetectedLanguage(string $errorOutput, ?string $specifiedLanguage): ?string
    {
        // If user specified a language (not 'auto'), return it
        if ($specifiedLanguage !== null && $specifiedLanguage !== 'auto') {
            return $specifiedLanguage;
        }

        // whisper.cpp outputs various formats depending on version:
        // "auto-detected language: en (p = 0.97)"
        // "whisper_full_with_state: auto-detected language = en"
        // "detected language: en"
        // "whisper_full_default: processing 1600 samples, 0.1 sec, 1 threads, 1 processors, lang = en, task = transcribe"
        
        $patterns = [
            '/auto-detected language:\s*(\w+)/i',
            '/auto-detected language\s*=\s*(\w+)/i',
            '/detected language:\s*(\w+)/i',
            '/lang\s*=\s*(\w{2,3})/i',
            '/language:\s*(\w{2,3})(?:\s|$|\()/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $errorOutput, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    private function buildResult(string $output, TranscriptionOptions $options, ?string $detectedLanguage): TranscriptionResult
    {
        if ($options->hasTimestamps() || $options->shouldDetectSpeakers()) {
            $segments = $this->parseTimestampedOutput($output, $options->shouldDetectSpeakers());
            $text = implode(' ', array_column($segments, 'text'));
            return new TranscriptionResult($text, $segments, $detectedLanguage);
        }

        return new TranscriptionResult($output, [], $detectedLanguage);
    }

    /**
     * Parse whisper output with timestamps into structured array.
     * Format: [00:00:00.000 --> 00:00:02.000]  Hello world
     * With tdrz: [SPEAKER_TURN] marker indicates speaker change
     *
     * @return array<int, array{start: string, end: string, text: string, speaker?: int}>
     */
    private function parseTimestampedOutput(string $output, bool $detectSpeakers = false): array
    {
        $segments = [];
        $pattern = '/\[(\d{2}:\d{2}:\d{2}\.\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}\.\d{3})\]\s*(.+)/';
        $currentSpeaker = 0;

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Detect speaker turn marker
            if ($detectSpeakers && str_contains($line, '[SPEAKER_TURN]')) {
                $currentSpeaker++;
                $line = str_replace('[SPEAKER_TURN]', '', $line);
            }

            if (preg_match($pattern, $line, $matches)) {
                $segment = [
                    'start' => $matches[1],
                    'end' => $matches[2],
                    'text' => trim($matches[3]),
                ];

                if ($detectSpeakers) {
                    $segment['speaker'] = $currentSpeaker;
                }

                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * @return array<string, string>
     */
    private function getWhisperEnvironment(): array
    {
        if ($this->platform->isWindows()) {
            return [];
        }

        $binDir = dirname($this->paths->getBinaryPath());
        $libDir = "{$binDir}/../lib";

        $env = [];

        if (is_dir($libDir)) {
            if ($this->platform->isMacOS()) {
                $currentPath = getenv('DYLD_LIBRARY_PATH') ?: '';
                $env['DYLD_LIBRARY_PATH'] = $currentPath ? "{$libDir}:{$currentPath}" : $libDir;
            } else {
                $currentPath = getenv('LD_LIBRARY_PATH') ?: '';
                $env['LD_LIBRARY_PATH'] = $currentPath ? "{$libDir}:{$currentPath}" : $libDir;
            }
        }

        return $env;
    }
}
