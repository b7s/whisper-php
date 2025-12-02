<?php

declare(strict_types=1);

namespace LaravelWhisper;

final class TranscriptionResult
{
    /**
     * @param array<int, array{start: string, end: string, text: string, speaker?: int}> $segments
     */
    public function __construct(
        private readonly string $text,
        private readonly array $segments = [],
        private readonly ?string $detectedLanguage = null,
    ) {}

    public function text(): string
    {
        return $this->text;
    }

    /**
     * @return array<int, array{start: string, end: string, text: string, speaker?: int}>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    public function detectedLanguage(): ?string
    {
        return $this->detectedLanguage;
    }

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

    public function toVtt(): string
    {
        $output = "WEBVTT\n\n";
        foreach ($this->segments as $segment) {
            $output .= $segment['start'] . ' --> ' . $segment['end'] . "\n";
            $output .= $segment['text'] . "\n\n";
        }
        return $output;
    }

    public function toJson(): string
    {
        return json_encode([
            'text' => $this->text,
            'segments' => $this->segments,
            'language' => $this->detectedLanguage,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

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

    private function formatSrtTime(string $time): string
    {
        // Convert HH:MM:SS.mmm to HH:MM:SS,mmm (SRT uses comma)
        return str_replace('.', ',', $time);
    }
}
