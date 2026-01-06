<?php

declare(strict_types=1);

namespace WhisperPHP;

final class TranscriptionResult
{
    /**
     * Initialize the transcription result with text, segments, and detected language.
     *
     * @param array<int, array{start: string, end: string, text: string, speaker?: int}> $segments
     */
    public function __construct(
        private readonly string $text,
        private readonly array $segments = [],
        private readonly ?string $detectedLanguage = null,
    ) {}

    /**
     * Get the transcription as plain text.
     */
    public function toText(): string
    {
        return $this->text;
    }

    /**
     * Get the transcription segments with timestamps.
     *
     * @return array<int, array{start: string, end: string, text: string, speaker?: int}>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Get the detected language of the audio.
     */
    public function detectedLanguage(): ?string
    {
        return $this->detectedLanguage;
    }

    /**
     * Convert the transcription to SRT subtitle format.
     */
    public function toSrt(): string
    {
        $output = '';
        foreach ($this->segments as $index => $segment) {
            $output .= ($index + 1) . "\n";
            $output .= $this->formatSrtTime($segment['start']) . ' --> ' . $this->formatSrtTime($segment['end']) . "\n";
            $output .= $segment['text'] . "\n\n";
        }
        return $output;
    }

    /**
     * Convert the transcription to WebVTT subtitle format.
     */
    public function toVtt(): string
    {
        $output = "WEBVTT\n\n";
        foreach ($this->segments as $segment) {
            $output .= $segment['start'] . ' --> ' . $segment['end'] . "\n";
            $output .= $segment['text'] . "\n\n";
        }
        return $output;
    }

    /**
     * Convert the transcription to JSON format.
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode([
            'text' => $this->text,
            'segments' => $this->segments,
            'language' => $this->detectedLanguage,
        ], $flags);

        return $json !== false ? $json : '{}';
    }

    /**
     * Convert the transcription to CSV format.
     */
    public function toCsv(): string
    {
        $output = "start,end,text\n";
        foreach ($this->segments as $segment) {
            $output .= sprintf(
                '"%s","%s","%s"' . "\n",
                $segment['start'],
                $segment['end'],
                str_replace('"', '""', $segment['text'])
            );
        }
        return $output;
    }

    /**
     * Save the transcription to a file in the specified format.
     */
    public function saveTo(string $path, ?string $format = null): bool
    {
        if ($format === null) {
            $format = pathinfo($path, PATHINFO_EXTENSION);
        }

        $content = match ($format) {
            'srt' => $this->toSrt(),
            'vtt' => $this->toVtt(),
            'json' => $this->toJson(),
            'csv' => $this->toCsv(),
            'txt' => $this->text,
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Format timestamp for SRT format (converts dot to comma).
     */
    private function formatSrtTime(string $time): string
    {
        // Convert HH:MM:SS.mmm to HH:MM:SS,mmm (SRT uses comma)
        return str_replace('.', ',', $time);
    }
}
