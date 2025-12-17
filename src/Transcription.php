<?php

declare(strict_types=1);

namespace LaravelWhisper;

use LaravelWhisper\Exceptions\WhisperException;

final class Transcription
{
    private TranscriptionOptions $options;
    private ?string $audioPath = null;

    /**
     * Initialize the transcription instance with a transcriber.
     */
    public function __construct(
        private readonly WhisperTranscriber $transcriber,
    ) {
        $this->options = new TranscriptionOptions();
    }

    /**
     * Set the audio or video file path to transcribe.
     */
    public function file(string $audioPath): self
    {
        $this->audioPath = $audioPath;
        return $this;
    }

    /**
     * Enable timestamps in the transcription output.
     */
    public function withTimestamps(): self
    {
        $this->options->withTimestamps();
        return $this;
    }

    /**
     * Translate the audio to English regardless of the source language.
     */
    public function toEnglish(): self
    {
        $this->options->toEnglish();
        return $this;
    }

    /**
     * Specify the source language of the audio.
     */
    public function fromLanguage(string $language): self
    {
        $this->options->fromLanguage($language);
        return $this;
    }

    /**
     * Provide context or prompt to guide the transcription.
     */
    public function context(string $prompt): self
    {
        $this->options->context($prompt);
        return $this;
    }

    /**
     * Improve decoding quality using beam search with specified beam size (1 to 10).
     */
    public function improveDecode(int $beamSize = 5): self
    {
        $this->options->improveDecode($beamSize);
        return $this;
    }

    /**
     * Set the sampling temperature for transcription (higher = more random).
     */
    public function temperature(float $temperature): self
    {
        $this->options->temperature($temperature);
        return $this;
    }

    /**
     * Filter out non-speech segments using voice activity detection (0.0 to 1.0).
     */
    public function filterNonSpeech(float $threshold = 0.5): self
    {
        $this->options->filterNonSpeech($threshold);
        return $this;
    }

    /**
     * Enable speaker diarization to identify different speakers.
     */
    public function detectSpeakers(): self
    {
        $this->options->detectSpeakers();
        return $this;
    }

    /**
     * Enable chunking for large audio/video files.
     * Automatically enabled for video files.
     *
     * @param int|null $sizeInBytes Chunk size in bytes (default: 20MB from Config)
     */
    public function chunk(?int $sizeInBytes = null): self
    {
        $this->options->chunk($sizeInBytes);
        return $this;
    }

    /**
     * Set a callback to track transcription progress.
     *
     * @param \Closure(int): void $callback
     */
    public function onProgress(\Closure $callback): self
    {
        $this->options->onProgress($callback);
        return $this;
    }

    /**
     * Set timeout for transcription process.
     *
     * @param int|null $seconds Timeout in seconds (null = no timeout/unlimited)
     */
    public function timeout(?int $seconds): self
    {
        $this->options->timeout($seconds);
        return $this;
    }

    /**
     * Execute the transcription and return the result object.
     *
     * @throws WhisperException
     */
    public function run(): TranscriptionResult
    {
        if ($this->audioPath === null) {
            throw new WhisperException('No audio file specified. Use file() method first.');
        }

        return $this->transcriber->transcribe($this->audioPath, $this->options);
    }

    /**
     * Execute transcription and return plain text output.
     *
     * @throws WhisperException
     */
    public function toText(): string
    {
        return $this->run()->toText();
    }

    /**
     * Execute transcription and return timestamped segments.
     *
     * @return array<int, array{start: string, end: string, text: string, speaker?: int}>
     * @throws WhisperException
     */
    public function segments(): array
    {
        $this->options->withTimestamps();
        return $this->run()->segments();
    }

    /**
     * Execute transcription and return SRT subtitle format.
     *
     * @throws WhisperException
     */
    public function toSrt(): string
    {
        $this->options->withTimestamps();
        return $this->run()->toSrt();
    }

    /**
     * Execute transcription and return WebVTT subtitle format.
     *
     * @throws WhisperException
     */
    public function toVtt(): string
    {
        $this->options->withTimestamps();
        return $this->run()->toVtt();
    }

    /**
     * Execute transcription and return JSON format.
     *
     * @throws WhisperException
     */
    public function toJson(bool $pretty = false): string
    {
        $this->options->withTimestamps();
        return $this->run()->toJson($pretty);
    }

    /**
     * Execute transcription and return CSV format.
     *
     * @throws WhisperException
     */
    public function toCsv(): string
    {
        $this->options->withTimestamps();
        return $this->run()->toCsv();
    }

    /**
     * Execute transcription and save the result to a file.
     *
     * @throws WhisperException
     */
    public function saveTo(string $path): bool
    {
        $this->options->withTimestamps();
        return $this->run()->saveTo($path);
    }

    /**
     * Detect and return the language of the audio.
     *
     * @throws WhisperException
     */
    public function detectLanguage(): ?string
    {
        return $this->run()->detectedLanguage();
    }
}
