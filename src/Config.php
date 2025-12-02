<?php

declare(strict_types=1);

namespace LaravelWhisper;

final class Config
{
    public function __construct(
        public readonly ?string $dataDir = null,
        public readonly ?string $binaryPath = null,
        public readonly ?string $modelPath = null,
        public readonly ?string $ffmpegPath = null,
        public readonly string $model = 'base',
        public readonly string $language = 'auto',
    ) {}
}
