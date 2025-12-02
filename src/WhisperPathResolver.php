<?php

declare(strict_types=1);

namespace LaravelWhisper;

use Symfony\Component\Process\Process;

final class WhisperPathResolver
{
    private string $dataDir;

    public function __construct(
        private readonly WhisperPlatformDetector $platform,
        private readonly Config $config,
    ) {
        $this->dataDir = $this->resolveDataDirectory();
    }

    public function getDataDirectory(): string
    {
        return $this->dataDir;
    }

    public function getBinaryPath(): string
    {
        if ($this->config->binaryPath && file_exists($this->config->binaryPath)) {
            return $this->config->binaryPath;
        }

        $possibleNames = $this->platform->isWindows()
            ? ['whisper-cli.exe', 'main.exe', 'whisper.exe']
            : ['whisper-cli', 'main', 'whisper'];

        foreach ($possibleNames as $name) {
            $path = "{$this->dataDir}/bin/{$name}";
            if (file_exists($path)) {
                return $path;
            }
        }

        return "{$this->dataDir}/bin/main";
    }

    public function getModelPath(): string
    {
        if ($this->config->modelPath && file_exists($this->config->modelPath)) {
            return $this->config->modelPath;
        }

        return "{$this->dataDir}/models/ggml-{$this->config->model}.bin";
    }

    public function getFfmpegPath(): string
    {
        if ($this->config->ffmpegPath && file_exists($this->config->ffmpegPath)) {
            return $this->config->ffmpegPath;
        }

        $which = $this->platform->isWindows() ? 'where' : 'which';
        $process = new Process([$which, 'ffmpeg']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        $binaryName = $this->platform->isWindows() ? 'ffmpeg.exe' : 'ffmpeg';

        return "{$this->dataDir}/bin/{$binaryName}";
    }

    public function getTempPath(string $prefix): string
    {
        return sys_get_temp_dir().'/'.$prefix.uniqid();
    }

    public function ensureDirectoriesExist(): void
    {
        $dirs = [
            $this->dataDir,
            dirname($this->getBinaryPath()),
            dirname($this->getModelPath()),
            dirname($this->getFfmpegPath()),
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function resolveDataDirectory(): string
    {
        if ($this->config->dataDir) {
            return $this->config->dataDir;
        }

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? getenv('HOME') ?: getenv('USERPROFILE');

        if (! $home) {
            return sys_get_temp_dir() . '/laravelwhisper';
        }

        return match ($this->platform->getOS()) {
            'darwin' => "{$home}/Library/Application Support/laravelwhisper",
            'windows' => ($_SERVER['LOCALAPPDATA'] ?? $_SERVER['APPDATA'] ?? "{$home}/AppData/Local") . '/laravelwhisper',
            default => "{$home}/.local/share/laravelwhisper",
        };
    }
}
