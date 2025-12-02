<?php

declare(strict_types=1);

namespace LaravelWhisper;

use LaravelWhisper\Exceptions\WhisperException;
use Symfony\Component\Process\Process;

final class WhisperTranscriber
{
    public function __construct(
        private readonly WhisperPlatformDetector $platform,
        private readonly WhisperPathResolver $paths,
        private readonly Logger $logger,
    ) {}

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

        $tempWavPath = $this->convertFileToWav($audioPath);

        try {
            return $this->runWhisper($tempWavPath, $options);
        } finally {
            @unlink($tempWavPath);
        }
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
     * @throws WhisperException
     */
    private function convertFileToWav(string $inputPath): string
    {
        $tempWavPath = $this->paths->getTempPath('audio_laravel_whisper_wav_') . '.wav';
        $ffmpegPath = $this->paths->getFfmpegPath();

        $process = new Process([
            $ffmpegPath,
            '-i', $inputPath,
            '-ar', '16000',
            '-ac', '1',
            '-c:a', 'pcm_s16le',
            '-y',
            $tempWavPath,
        ]);
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
            $args = array_filter($args, fn ($arg) => $arg !== '-ng');
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

        // GPU support
        if ($this->platform->hasGpuSupport()) {
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
