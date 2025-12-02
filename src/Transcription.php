<?php

declare(strict_types=1);

namespace LaravelWhisper;

use LaravelWhisper\Exceptions\WhisperException;

final class Transcription
{
    private TranscriptionOptions $options;
    private ?string $audioPath = null;

    public function __construct(
        private readonly WhisperTranscriber $transcriber,
    ) {
        $this->options = new TranscriptionOptions();
    }

    public function file(string $audioPath): self
    {
        $this->audioPath = $audioPath;
        return $this;
    }

    public function withTimestamps(): self
    {
        $this->options->withTimestamps();
        return $this;
    }

    public function toEnglish(): self
    {
        $this->options->toEnglish();
        return $this;
    }

    public function fromLanguage(string $language): self
    {
        $this->options->fromLanguage($language);
        return $this;
    }

    public function context(string $prompt): self
    {
        $this->options->context($prompt);
        return $this;
    }

    public function improveDecode(int $beamSize = 5): self
    {
        $this->options->improveDecode($beamSize);
        return $this;
    }

    public function temperature(float $temperature): self
    {
        $this->options->temperature($temperature);
        return $this;
    }

    public function filterNonSpeech(float $threshold = 0.5): self
    {
        $this->options->filterNonSpeech($threshold);
        return $this;
    }

    public function detectSpeakers(): self
    {
        $this->options->detectSpeakers();
        return $this;
    }

    /**
     * @param \Closure(int): void $callback
     */
    public function onProgress(\Closure $callback): self
    {
        $this->options->onProgress($callback);
        return $this;
    }

    /**
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
     * @throws WhisperException
     */
    public function text(): string
    {
        return $this->run()->text();
    }

    /**
     * @return array<int, array{start: string, end: string, text: string, speaker?: int}>
     * @throws WhisperException
     */
    public function segments(): array
    {
        $this->options->withTimestamps();
        return $this->run()->segments();
    }

    /**
     * @throws WhisperException
     */
    public function toSrt(): string
    {
        $this->options->withTimestamps();
        return $this->run()->toSrt();
    }

    /**
     * @throws WhisperException
     */
    public function toVtt(): string
    {
        $this->options->withTimestamps();
        return $this->run()->toVtt();
    }

    /**
     * @throws WhisperException
     */
    public function toJson(bool $pretty = true): string
    {
        $this->options->withTimestamps();
        return $this->run()->toJson($pretty);
    }

    /**
     * @throws WhisperException
     */
    public function toCsv(): string
    {
        $this->options->withTimestamps();
        return $this->run()->toCsv();
    }

    /**
     * @throws WhisperException
     */
    public function saveTo(string $path): bool
    {
        $this->options->withTimestamps();
        return $this->run()->saveTo($path);
    }

    /**
     * @throws WhisperException
     */
    public function detectLanguage(): ?string
    {
        return $this->run()->detectedLanguage();
    }
}
