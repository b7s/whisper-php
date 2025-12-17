<?php

declare(strict_types=1);

namespace LaravelWhisper;

final class Config
{
    /** @var int Default chunk size in bytes (20MB) */
    public const DEFAULT_CHUNK_SIZE = 20 * 1024 * 1024;

    public function __construct(
        public readonly ?string $dataDir = null,
        public readonly ?string $binaryPath = null,
        public readonly ?string $modelPath = null,
        public readonly ?string $ffmpegPath = null,
        public readonly string $model = 'base',
        public readonly string $language = 'auto',
        public readonly int $chunkSize = self::DEFAULT_CHUNK_SIZE,
    ) {}
}
