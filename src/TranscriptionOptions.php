<?php

declare(strict_types=1);

namespace LaravelWhisper;

final class TranscriptionOptions
{
    private bool $withTimestamps = false;
    private bool $translate = false;
    private ?string $outputFormat = null;
    private ?string $language = null;
    private ?string $initialPrompt = null;
    private bool $useBeamSearch = false;
    private int $beamSize = 5;
    private float $temperature = 0.0;
    private bool $enableVad = false;
    private float $vadThreshold = 0.5;
    private bool $detectSpeakerChanges = false;
    /** @var (\Closure(int): void)|null */
    private ?\Closure $progressCallback = null;

    public function withTimestamps(bool $enabled = true): self
    {
        $this->withTimestamps = $enabled;
        return $this;
    }

    public function toEnglish(bool $enabled = true): self
    {
        $this->translate = $enabled;
        return $this;
    }

    public function outputFormat(string $format): self
    {
        $validFormats = ['srt', 'vtt', 'json', 'csv', 'txt'];
        if (!in_array($format, $validFormats, true)) {
            throw new \InvalidArgumentException("Invalid format. Must be one of: " . implode(', ', $validFormats));
        }
        $this->outputFormat = $format;
        return $this;
    }

    public function fromLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function context(string $prompt): self
    {
        $this->initialPrompt = $prompt;
        return $this;
    }

    public function improveDecode(int $beamSize = 5): self
    {
        $this->useBeamSearch = true;
        $this->beamSize = max(1, $beamSize > 10 ? 10 : $beamSize);
        return $this;
    }

    public function temperature(float $temperature): self
    {
        $this->temperature = max(0.0, min(1.0, $temperature));
        return $this;
    }

    public function filterNonSpeech(float $threshold = 0.5): self
    {
        $this->enableVad = true;
        $this->vadThreshold = max(0.0, min(1.0, $threshold));
        return $this;
    }

    public function detectSpeakers(bool $enabled = true): self
    {
        $this->detectSpeakerChanges = $enabled;
        return $this;
    }

    /**
     * @param \Closure(int): void $callback
     */
    public function onProgress(\Closure $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    public function hasTimestamps(): bool
    {
        return $this->withTimestamps;
    }

    public function shouldTranslate(): bool
    {
        return $this->translate;
    }

    public function getOutputFormat(): ?string
    {
        return $this->outputFormat;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getInitialPrompt(): ?string
    {
        return $this->initialPrompt;
    }

    public function shouldUseBeamSearch(): bool
    {
        return $this->useBeamSearch;
    }

    public function getBeamSize(): int
    {
        return $this->beamSize;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function isVadEnabled(): bool
    {
        return $this->enableVad;
    }

    public function getVadThreshold(): float
    {
        return $this->vadThreshold;
    }

    public function shouldDetectSpeakers(): bool
    {
        return $this->detectSpeakerChanges;
    }

    /**
     * @return (\Closure(int): void)|null
     */
    public function getProgressCallback(): ?\Closure
    {
        return $this->progressCallback;
    }
}
