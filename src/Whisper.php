<?php

declare(strict_types=1);

namespace LaravelWhisper;

use LaravelWhisper\Exceptions\WhisperException;

final class Whisper
{
    private readonly WhisperPlatformDetector $platform;
    private WhisperPathResolver $paths;
    private readonly WhisperDownloader $downloader;
    private WhisperTranscriber $transcriber;
    private readonly Logger $logger;
    private Config $config;

    public function __construct(?Config $config = null, ?Logger $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->config = $config ?? new Config();

        $this->platform = new WhisperPlatformDetector();
        $this->paths = new WhisperPathResolver($this->platform, $this->config);
        $this->downloader = new WhisperDownloader($this->platform, $this->paths, $this->logger);
        $this->transcriber = new WhisperTranscriber($this->platform, $this->paths, $this->logger, $this->config->chunkSize);
    }

    /**
     * Start a fluent transcription builder.
     */
    public function audio(string $audioPath): Transcription
    {
        return (new Transcription($this->transcriber))->file($audioPath);
    }

    /**
     * Start a fluent transcription builder for video files.
     * Automatically extracts audio and enables chunking.
     */
    public function video(string $videoPath): Transcription
    {
        return (new Transcription($this->transcriber))->file($videoPath)->chunk();
    }

    /**
     * Check if a file is a video based on extension.
     */
    public function isVideoFile(string $filePath): bool
    {
        return $this->transcriber->isVideoFile($filePath);
    }

    /**
     * @throws WhisperException
     * @return string|array<int, array{start: string, end: string, text: string}>
     * @deprecated Use audio()->toText() or audio()->segments() instead
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
     * @return array{binary: bool, model: bool, current_model: string, available_models: array<string>, ffmpeg: bool, gpu: bool}
     */
    public function getStatus(): array
    {
        return [
            'binary' => file_exists($this->paths->getBinaryPath()),
            'model' => file_exists($this->paths->getModelPath()),
            'binary_path' => $this->paths->getBinaryPath(),
            'current_model' => $this->getCurrentModel(),
            'available_models' => $this->getAvailableModels(),
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

    /**
     * Switch to a different model. Downloads the model if not available.
     *
     * @throws WhisperException
     */
    public function useModel(string $model = 'base'): self
    {
        // Update config with new model
        $this->config = new Config(
            dataDir: $this->config->dataDir,
            binaryPath: $this->config->binaryPath,
            modelPath: $this->config->modelPath,
            ffmpegPath: $this->config->ffmpegPath,
            model: $model,
            language: $this->config->language,
            chunkSize: $this->config->chunkSize,
        );

        // Recreate paths and transcriber with new config
        $this->paths = new WhisperPathResolver($this->platform, $this->config);
        $this->transcriber = new WhisperTranscriber($this->platform, $this->paths, $this->logger, $this->config->chunkSize);

        // Download model if not available
        if (!file_exists($this->paths->getModelPath())) {
            $this->logger->info("Model [{$model}] not found, downloading (this may take some time)...");
            $this->downloadModel($model);
        }

        return $this;
    }

    /**
     * Get the currently configured model name.
     */
    public function getCurrentModel(): string
    {
        return $this->config->model;
    }

    /**
     * Get list of available (downloaded) models.
     *
     * @return array<string>
     */
    public function getAvailableModels(): array
    {
        $modelsDir = dirname($this->paths->getModelPath());
        if (!is_dir($modelsDir)) {
            return [];
        }

        $models = [];
        $files = scandir($modelsDir);
        
        if ($files === false) {
            return [];
        }
        
        foreach ($files as $file) {
            if (preg_match('/^ggml-(.+)\.bin$/', $file, $matches)) {
                $models[] = $matches[1];
            }
        }

        return $models;
    }

    /**
     * Check if a specific model is downloaded.
     */
    public function hasModel(string $model): bool
    {
        $modelPath = dirname($this->paths->getModelPath()) . "/ggml-{$model}.bin";
        return file_exists($modelPath);
    }

    public function getFfmpegPath(): string
    {
        return $this->paths->getFfmpegPath();
    }

    /**
     * Get the path to a specific model file.
     */
    public function getModelPath(?string $model = null): string
    {
        $model ??= $this->config->model;
        return dirname($this->paths->getModelPath()) . "/ggml-{$model}.bin";
    }

    /**
     * Delete a model file to force re-download.
     */
    public function deleteModel(string $model): bool
    {
        $modelPath = $this->getModelPath($model);
        if (file_exists($modelPath)) {
            return @unlink($modelPath);
        }
        return true;
    }

    /**
     * Re-download a model (deletes existing and downloads fresh).
     *
     * @throws WhisperException
     */
    public function redownloadModel(string $model): bool
    {
        $this->deleteModel($model);
        return $this->downloadModel($model);
    }
}
