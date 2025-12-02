<?php

declare(strict_types=1);

namespace LaravelWhisper;

use LaravelWhisper\Exceptions\WhisperException;

final class Whisper
{
    private readonly WhisperPlatformDetector $platform;
    private readonly WhisperPathResolver $paths;
    private readonly WhisperDownloader $downloader;
    private readonly WhisperTranscriber $transcriber;
    private readonly Logger $logger;

    public function __construct(?Config $config = null, ?Logger $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $config ??= new Config();

        $this->platform = new WhisperPlatformDetector();
        $this->paths = new WhisperPathResolver($this->platform, $config);
        $this->downloader = new WhisperDownloader($this->platform, $this->paths, $this->logger);
        $this->transcriber = new WhisperTranscriber($this->platform, $this->paths, $this->logger);
    }

    /**
     * Start a fluent transcription builder.
     */
    public function audio(string $audioPath): Transcription
    {
        return (new Transcription($this->transcriber))->file($audioPath);
    }

    /**
     * @throws WhisperException
     * @return string|array<int, array{start: string, end: string, text: string}>
     * @deprecated Use audio()->text() or audio()->segments() instead
     */
    public function transcribe(string $audioPath, bool $withTimestamps = false): string|array
    {
        return $this->transcriber->transcribeFromPath($audioPath, $withTimestamps);
    }

    /**
     * Transcribe audio file and return segments with timestamps.
     *
     * @return array<int, array{start: string, end: string, text: string}>
     * @throws WhisperException
     * @deprecated Use audio()->segments() instead
     */
    public function transcribeWithTimestamps(string $audioPath): array
    {
        return $this->transcriber->transcribeWithTimestamps($audioPath);
    }

    public function isAvailable(): bool
    {
        return $this->transcriber->isAvailable();
    }

    public function hasGpuSupport(): bool
    {
        return $this->platform->hasGpuSupport();
    }

    /**
     * @return array{binary: bool, model: bool, ffmpeg: bool, gpu: bool}
     */
    public function getStatus(): array
    {
        return [
            'binary' => file_exists($this->paths->getBinaryPath()),
            'model' => file_exists($this->paths->getModelPath()),
            'ffmpeg' => $this->downloader->isFfmpegAvailable(),
            'gpu' => $this->platform->hasGpuSupport(),
        ];
    }

    /**
     * @throws WhisperException
     */
    public function setup(): bool
    {
        $this->paths->ensureDirectoriesExist();

        $ffmpegDownloaded = $this->downloader->downloadFfmpeg();
        $binaryDownloaded = $this->downloader->downloadBinary();
        $modelDownloaded = $this->downloader->downloadModel();

        return $ffmpegDownloaded && $binaryDownloaded && $modelDownloaded;
    }

    /**
     * @throws WhisperException
     */
    public function downloadFfmpeg(): bool
    {
        return $this->downloader->downloadFfmpeg();
    }

    /**
     * @throws WhisperException
     */
    public function downloadBinary(): bool
    {
        $result = $this->downloader->downloadBinary();

        if ($result) {
            $this->downloader->fixLibrarySymlinks();
        }

        return $result;
    }

    public function fixLibrarySymlinks(): void
    {
        $this->downloader->fixLibrarySymlinks();
    }

    /**
     * @throws WhisperException
     */
    public function downloadModel(string $model = 'base'): bool
    {
        return $this->downloader->downloadModel($model);
    }

    public function getFfmpegPath(): string
    {
        return $this->paths->getFfmpegPath();
    }
}
