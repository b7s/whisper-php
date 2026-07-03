<?php

declare(strict_types=1);

namespace WhisperPHP;

use Symfony\Component\Process\Process;
use WhisperPHP\Exceptions\WhisperException;

final class VoiceToneAnalyzer
{
    public function __construct(
        private readonly WhisperPathResolver $paths,
        private readonly Logger $logger,
    ) {}

    /**
     * Analyze voice tone for each transcription segment.
     *
     * @param array<int, array{start: string, end: string, text: string, speaker?: int}> $segments
     * @return array{
     *   has_shouting: bool,
     *   has_soft_speaking: bool,
     *   shouting: list<array{start: string, end: string, db: float, text: string}>,
     *   soft: list<array{start: string, end: string, db: float, text: string}>,
     *   segments: list<array{start: string, end: string, db: float, tone: string, text: string}>,
     * }
     */
    public function analyze(string $audioPath, array $segments, float $shoutThresholdDb = -10.0, float $softThresholdDb = -30.0): array
    {
        $wavPath = $this->convertToWav($audioPath);
        try {
            return $this->analyzeWav($wavPath, $segments, $shoutThresholdDb, $softThresholdDb);
        } finally {
            @unlink($wavPath);
        }
    }

    /**
     * Analyze voice tone from an already-converted WAV file.
     *
     * @param array<int, array{start: string, end: string, text: string, speaker?: int}> $segments
     * @return array{
     *   has_shouting: bool,
     *   has_soft_speaking: bool,
     *   shouting: list<array{start: string, end: string, db: float, text: string}>,
     *   soft: list<array{start: string, end: string, db: float, text: string}>,
     *   segments: list<array{start: string, end: string, db: float, tone: string, text: string}>,
     * }
     * @throws WhisperException
     */
    public function analyzeWav(string $wavPath, array $segments, float $shoutThresholdDb = -10.0, float $softThresholdDb = -30.0): array
    {
        if (empty($segments) || !file_exists($wavPath)) {
            return $this->emptyResult();
        }

        $samples = $this->readWavSamples($wavPath);
        $dataSize = count($samples);

        if ($dataSize === 0) {
            return $this->emptyResult();
        }

        $shouting = [];
        $soft = [];
        $allSegments = [];
        $hasShouting = false;
        $hasSoftSpeaking = false;

        $samplesPerMs = 16000 / 1000;

        foreach ($segments as $segment) {
            $startSample = (int) round($this->timestampToMs($segment['start']) * $samplesPerMs);
            $endSample = (int) round($this->timestampToMs($segment['end']) * $samplesPerMs);

            $startSample = max(0, min($startSample, $dataSize - 1));
            $endSample = max($startSample + 1, min($endSample, $dataSize));

            $segmentSamples = array_slice($samples, $startSample, $endSample - $startSample);
            $rms = $this->computeRms($segmentSamples);
            $db = $rms > 0.0 ? round(20.0 * log10($rms / 32767.0), 2) : -100.0;

            $tone = $this->classifyTone($db, $shoutThresholdDb, $softThresholdDb);

            $entry = [
                'start' => $segment['start'],
                'end' => $segment['end'],
                'db' => $db,
                'text' => $segment['text'],
            ];

            $allSegments[] = [
                'start' => $entry['start'],
                'end' => $entry['end'],
                'db' => $entry['db'],
                'tone' => $tone,
                'text' => $entry['text'],
            ];

            if ($tone === 'shouting') {
                $shouting[] = $entry;
                $hasShouting = true;
            } elseif ($tone === 'soft') {
                $soft[] = $entry;
                $hasSoftSpeaking = true;
            }
        }

        return [
            'has_shouting' => $hasShouting,
            'has_soft_speaking' => $hasSoftSpeaking,
            'shouting' => $shouting,
            'soft' => $soft,
            'segments' => $allSegments,
        ];
    }

    /**
     * @return array{
     *   has_shouting: false,
     *   has_soft_speaking: false,
     *   shouting: list<never>,
     *   soft: list<never>,
     *   segments: list<never>,
     * }
     */
    private function emptyResult(): array
    {
        return [
            'has_shouting' => false,
            'has_soft_speaking' => false,
            'shouting' => [],
            'soft' => [],
            'segments' => [],
        ];
    }

    private function classifyTone(float $db, float $shoutThresholdDb, float $softThresholdDb): string
    {
        if ($db > $shoutThresholdDb) {
            return 'shouting';
        }
        if ($db < $softThresholdDb) {
            return 'soft';
        }
        return 'normal';
    }

    /**
     * @param array<int, int> $samples
     */
    private function computeRms(array $samples): float
    {
        if (empty($samples)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($samples as $sample) {
            $sum += $sample * $sample;
        }
        return sqrt($sum / count($samples));
    }

    /**
     * @return array<int, int>
     * @throws WhisperException
     */
    private function readWavSamples(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new WhisperException("Cannot read WAV file: {$path}");
        }

        try {
            $riff = fread($handle, 4);
            if ($riff !== 'RIFF') {
                throw new WhisperException('Not a valid WAV file (missing RIFF header)');
            }

            fread($handle, 4);
            $wave = fread($handle, 4);
            if ($wave !== 'WAVE') {
                throw new WhisperException('Not a valid WAV file (missing WAVE identifier)');
            }

            $fmtId = fread($handle, 4);
            if ($fmtId !== 'fmt ') {
                throw new WhisperException('Not a valid WAV file (missing fmt chunk)');
            }

            $fmtSize = $this->readUint32($handle);
            $this->readLine($handle, 2);
            $this->readLine($handle, 2);
            $sampleRate = $this->readUint32($handle);
            $this->readLine($handle, 4);
            $this->readLine($handle, 2);
            $bitsPerSample = $this->readUint16($handle);

            if ($fmtSize > 16) {
                $this->readLine($handle, $fmtSize - 16);
            }

            $chunkSize = 0;
            while (true) {
                $chunkId = fread($handle, 4);
                if ($chunkId === false || strlen($chunkId) < 4) {
                    throw new WhisperException('WAV file has no data chunk');
                }
                $chunkSize = $this->readUint32($handle);
                if ($chunkId === 'data') {
                    break;
                }
                fseek($handle, $chunkSize, SEEK_CUR);
            }

            return $this->unpackSamples($handle, $chunkSize, $bitsPerSample);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @throws WhisperException
     */
    private function readLine(mixed $handle, int $size): string
    {
        $data = fread($handle, max(1, $size));
        if ($data === false) {
            throw new WhisperException('Failed to read from WAV file');
        }
        return $data;
    }

    /**
     * @throws WhisperException
     */
    private function readUint32(mixed $handle): int
    {
        $data = $this->readLine($handle, 4);
        $unpacked = unpack('V', $data);
        return $unpacked !== false ? $unpacked[1] : 0;
    }

    /**
     * @throws WhisperException
     */
    private function readUint16(mixed $handle): int
    {
        $data = $this->readLine($handle, 2);
        $unpacked = unpack('v', $data);
        return $unpacked !== false ? $unpacked[1] : 0;
    }

    /**
     * @return array<int, int>
     * @throws WhisperException
     */
    private function unpackSamples(mixed $handle, int $dataSize, int $bitsPerSample): array
    {
        if ($dataSize <= 0) {
            return [];
        }

        $data = fread($handle, $dataSize);
        if ($data === false || strlen($data) === 0) {
            return [];
        }

        return match ($bitsPerSample) {
            8 => $this->unpack8Bit($data),
            16 => $this->unpack16Bit($data),
            32 => $this->unpack32Bit($data),
            default => throw new WhisperException("Unsupported bits per sample: {$bitsPerSample}"),
        };
    }

    /**
     * @return array<int, int>
     */
    private function unpack8Bit(string $data): array
    {
        $unsigned = unpack('C*', $data);
        if ($unsigned === false) {
            return [];
        }
        return array_map(fn(int $v): int => ($v - 128) << 8, array_values($unsigned));
    }

    /**
     * @return array<int, int>
     */
    private function unpack16Bit(string $data): array
    {
        $unsigned = unpack('v*', $data);
        if ($unsigned === false) {
            return [];
        }
        $samples = [];
        foreach ($unsigned as $v) {
            $samples[] = $v >= 0x8000 ? $v - 0x10000 : $v;
        }
        return $samples;
    }

    /**
     * @return array<int, int>
     */
    private function unpack32Bit(string $data): array
    {
        $signed = unpack('l*', $data);
        if ($signed === false) {
            return [];
        }
        return array_values($signed);
    }

    /**
     * @throws WhisperException
     */
    private function convertToWav(string $audioPath): string
    {
        $outputPath = $this->paths->getTempPath('voice_tone_wav_') . '.wav';
        $ffmpegPath = $this->paths->getFfmpegPath();

        $process = new Process([
            $ffmpegPath,
            '-i', $audioPath,
            '-ar', '16000',
            '-ac', '1',
            '-c:a', 'pcm_s16le',
            '-y',
            $outputPath,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->error('Failed to convert audio for voice tone analysis', [
                'error' => $process->getErrorOutput(),
            ]);
            throw new WhisperException('Failed to convert audio for voice tone analysis');
        }

        return $outputPath;
    }

    private function timestampToMs(string $timestamp): float
    {
        if (preg_match('/(\d{2}):(\d{2}):(\d{2})\.(\d{3})/', $timestamp, $matches)) {
            return (float) $matches[1] * 3600000.0
                 + (float) $matches[2] * 60000.0
                 + (float) $matches[3] * 1000.0
                 + (float) $matches[4];
        }
        return 0.0;
    }
}
