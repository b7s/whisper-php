<?php

declare(strict_types=1);

namespace WhisperPHP;

use WhisperPHP\Exceptions\WhisperException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

final class WhisperDownloader
{
    /**
     * Minimum expected sizes for each model in bytes.
     * These are approximate sizes to detect corrupted/incomplete downloads.
     * @var array<string, int>
     */
    private const MODEL_MIN_SIZES = [
        'tiny' => 70_000_000,      // ~75MB
        'tiny.en' => 70_000_000,
        'base' => 130_000_000,     // ~142MB
        'base.en' => 130_000_000,
        'small' => 450_000_000,    // ~466MB
        'small.en' => 450_000_000,
        'medium' => 1_400_000_000, // ~1.5GB
        'medium.en' => 1_400_000_000,
        'large' => 2_800_000_000,  // ~3GB
        'large-v1' => 2_800_000_000,
        'large-v2' => 2_800_000_000,
        'large-v3' => 2_800_000_000,
        'large-v3-turbo' => 1_500_000_000, // ~1.6GB
    ];

    public function __construct(
        private readonly WhisperPlatformDetector $platform,
        private readonly WhisperPathResolver $paths,
        private readonly Logger $logger,
    ) {}

    /**
     * @throws WhisperException
     */
    public function downloadFfmpeg(): bool
    {
        $ffmpegPath = $this->paths->getFfmpegPath();

        if ($this->isFfmpegAvailable()) {
            return true;
        }

        if (file_exists($ffmpegPath)) {
            return true;
        }

        $this->paths->ensureDirectoriesExist();

        $downloadUrl = $this->getFfmpegDownloadUrl();

        if (! $downloadUrl) {
            $os = $this->platform->getOS();
            $arch = $this->platform->getArch();

            $this->logger->error('No FFmpeg download URL for this platform', ['os' => $os, 'arch' => $arch]);

            throw new WhisperException(
                'FFmpeg download not available for this platform',
                "OS: {$os}, Architecture: {$arch}. Please install FFmpeg manually."
            );
        }

        $this->logger->info('Downloading FFmpeg', ['url' => $downloadUrl]);

        $extension = str_ends_with($downloadUrl, '.tar.xz') ? '.tar.xz' : '.zip';
        $tempFile = $this->paths->getTempPath('ffmpeg_download_').$extension;

        $this->downloadFile($downloadUrl, $tempFile, 'FFmpeg');

        if (str_ends_with($downloadUrl, '.zip')) {
            return $this->extractFfmpegZip($tempFile);
        }

        if (str_ends_with($downloadUrl, '.tar.xz')) {
            return $this->extractFfmpegTarXz($tempFile);
        }

        @unlink($tempFile);

        return false;
    }

    /**
     * @throws WhisperException
     */
    public function downloadBinary(): bool
    {
        $binaryPath = $this->paths->getBinaryPath();

        if (file_exists($binaryPath)) {
            return true;
        }

        $this->paths->ensureDirectoriesExist();

        $downloadUrl = $this->getBinaryDownloadUrl();

        if (! $downloadUrl) {
            $os = $this->platform->getOS();
            if ($os === 'linux' || $os === 'darwin') {
                return $this->compileFromSource();
            }

            $arch = $this->platform->getArch();
            $this->logger->error('No binary download URL for this platform', ['os' => $os, 'arch' => $arch]);

            throw new WhisperException(
                'Whisper binary download not found for this platform',
                "OS: {$os}, Architecture: {$arch}"
            );
        }

        $this->logger->info('Downloading whisper binary', ['url' => $downloadUrl]);

        $extension = str_ends_with($downloadUrl, '.tar.gz') ? '.tar.gz' : '.zip';
        $tempFile = $this->paths->getTempPath('whisper_download_').$extension;

        $this->downloadFile($downloadUrl, $tempFile, 'Whisper binary');

        if (str_ends_with($downloadUrl, '.zip')) {
            return $this->extractBinaryZip($tempFile);
        }

        if (str_ends_with($downloadUrl, '.tar.gz')) {
            return $this->extractBinaryTarGz($tempFile);
        }

        rename($tempFile, $binaryPath);
        chmod($binaryPath, 0755);

        return true;
    }

    /**
     * @throws WhisperException
     */
    public function downloadModel(string $model = 'base'): bool
    {
        $modelsDir = dirname($this->paths->getModelPath());
        $modelPath = "{$modelsDir}/ggml-{$model}.bin";

        if (file_exists($modelPath)) {
            return true;
        }

        $this->paths->ensureDirectoriesExist();

        $modelUrl = "https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-{$model}.bin";

        $this->logger->info('Downloading whisper model', ['model' => $model, 'url' => $modelUrl]);

        $process = new Process([
            'curl', '-L', '-f',
            '--retry', '3',
            '--retry-delay', '2',
            '-o', $modelPath,
            $modelUrl,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $this->logger->error('Failed to download Whisper model', [
                'model' => $model,
                'error' => $error,
                'output' => $process->getOutput(),
            ]);
            @unlink($modelPath);

            throw new WhisperException(
                'Failed to download Whisper model',
                $error ?: 'Network error or server unavailable'
            );
        }

        $expectedMinSize = self::MODEL_MIN_SIZES[$model] ?? 10_000_000;
        $actualSize = file_exists($modelPath) ? filesize($modelPath) : 0;

        if (! file_exists($modelPath) || $actualSize < $expectedMinSize) {
            $expectedMB = round($expectedMinSize / 1_000_000);
            $actualMB = round($actualSize / 1_000_000);
            $this->logger->error('Downloaded model file is invalid or incomplete', [
                'path' => $modelPath,
                'actual_size' => $actualSize,
                'expected_min_size' => $expectedMinSize,
            ]);
            @unlink($modelPath);

            throw new WhisperException(
                'Downloaded model file is invalid or incomplete',
                "File size: {$actualMB}MB (expected > {$expectedMB}MB for model '{$model}'). Download may have been interrupted."
            );
        }

        return true;
    }

    public function isFfmpegAvailable(): bool
    {
        $ffmpegPath = $this->paths->getFfmpegPath();

        if (file_exists($ffmpegPath)) {
            return true;
        }

        $which = $this->platform->isWindows() ? 'where' : 'which';
        $process = new Process([$which, 'ffmpeg']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @throws WhisperException
     */
    private function downloadFile(string $url, string $destination, string $component): void
    {
        $process = new Process([
            'curl', '-L', '-f',
            '--retry', '3',
            '--retry-delay', '2',
            '-o', $destination,
            $url,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $this->logger->error("Failed to download {$component}", [
                'url' => $url,
                'error' => $error,
            ]);
            @unlink($destination);

            throw new WhisperException(
                "Failed to download {$component}",
                $error ?: 'Network error or server unavailable'
            );
        }

        if (! file_exists($destination) || filesize($destination) < 1000) {
            $size = file_exists($destination) ? filesize($destination) : 0;
            $this->logger->error("Downloaded {$component} file is invalid", [
                'path' => $destination,
                'size' => $size,
            ]);
            @unlink($destination);

            throw new WhisperException(
                "Downloaded {$component} file is invalid",
                "File size: {$size} bytes. Download may have been interrupted."
            );
        }
    }

    private function getFfmpegDownloadUrl(): ?string
    {
        $baseUrl = 'https://github.com/BtbN/FFmpeg-Builds/releases/download/latest';
        $os = $this->platform->getOS();
        $arch = $this->platform->getArch();

        return match ($os) {
            'windows' => match ($arch) {
                'x86_64', 'amd64' => "{$baseUrl}/ffmpeg-master-latest-win64-gpl.zip",
                default => null,
            },
            'linux' => match ($arch) {
                'x86_64', 'amd64' => "{$baseUrl}/ffmpeg-master-latest-linux64-gpl.tar.xz",
                'arm64', 'aarch64' => "{$baseUrl}/ffmpeg-master-latest-linuxarm64-gpl.tar.xz",
                default => null,
            },
            default => null,
        };
    }

    private function getBinaryDownloadUrl(): ?string
    {
        $baseUrl = 'https://github.com/ggerganov/whisper.cpp/releases/latest/download';
        $os = $this->platform->getOS();
        $arch = $this->platform->getArch();

        return match ($os) {
            'windows' => match ($arch) {
                'x86_64', 'amd64' => "{$baseUrl}/whisper-bin-x64.zip",
                default => "{$baseUrl}/whisper-bin-Win32.zip",
            },
            default => null,
        };
    }

    /**
     * @throws WhisperException
     */
    private function extractFfmpegZip(string $zipPath): bool
    {
        $extractDir = dirname($this->paths->getFfmpegPath());

        $process = $this->platform->isWindows()
            ? new Process(['powershell', '-Command', "Expand-Archive -Path '{$zipPath}' -DestinationPath '{$extractDir}' -Force"])
            : new Process(['unzip', '-o', $zipPath, '-d', $extractDir]);
        
        $process->run();
        @unlink($zipPath);

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $this->logger->error('Failed to extract FFmpeg', ['error' => $error]);

            throw new WhisperException('Failed to extract FFmpeg', $error);
        }

        $this->findAndRenameFfmpeg($extractDir);

        return file_exists($this->paths->getFfmpegPath());
    }

    /**
     * @throws WhisperException
     */
    private function extractFfmpegTarXz(string $tarPath): bool
    {
        $extractDir = dirname($this->paths->getFfmpegPath());

        $process = new Process(['tar', '-xJf', $tarPath, '-C', $extractDir]);
        $process->run();
        @unlink($tarPath);

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $this->logger->error('Failed to extract FFmpeg', ['error' => $error]);

            throw new WhisperException('Failed to extract FFmpeg', $error);
        }

        $this->findAndRenameFfmpeg($extractDir);

        return file_exists($this->paths->getFfmpegPath());
    }

    private function findAndRenameFfmpeg(string $dir): void
    {
        $ffmpegPath = $this->paths->getFfmpegPath();
        $possibleNames = ['ffmpeg', 'ffmpeg.exe'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (\in_array($file->getFilename(), $possibleNames, true)) {
                if ($file->getPathname() !== $ffmpegPath) {
                    rename($file->getPathname(), $ffmpegPath);
                    chmod($ffmpegPath, 0755);
                }

                return;
            }
        }
    }

    /**
     * @throws WhisperException
     */
    private function extractBinaryZip(string $zipPath): bool
    {
        $extractDir = dirname($this->paths->getBinaryPath());

        $process = $this->platform->isWindows()
            ? new Process(['powershell', '-Command', "Expand-Archive -Path '{$zipPath}' -DestinationPath '{$extractDir}' -Force"])
            : new Process(['unzip', '-o', $zipPath, '-d', $extractDir]);
        
        $process->run();
        @unlink($zipPath);

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $this->logger->error('Failed to extract Whisper binary', ['error' => $error]);

            throw new WhisperException('Failed to extract Whisper binary', $error);
        }

        $this->findAndRenameBinary($extractDir);

        return file_exists($this->paths->getBinaryPath());
    }

    /**
     * @throws WhisperException
     */
    private function extractBinaryTarGz(string $tarPath): bool
    {
        $extractDir = dirname($this->paths->getBinaryPath());

        $process = new Process(['tar', '-xzf', $tarPath, '-C', $extractDir]);
        $process->run();
        @unlink($tarPath);

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $this->logger->error('Failed to extract Whisper binary', ['error' => $error]);

            throw new WhisperException('Failed to extract Whisper binary', $error);
        }

        $this->findAndRenameBinary($extractDir);

        return file_exists($this->paths->getBinaryPath());
    }

    private function findAndRenameBinary(string $dir): void
    {
        $binaryPath = $this->paths->getBinaryPath();
        $possibleNames = ['whisper', 'whisper.exe', 'main', 'main.exe', 'whisper-cli', 'whisper-cli.exe'];

        foreach ($possibleNames as $name) {
            $path = "{$dir}/{$name}";
            if (file_exists($path) && $path !== $binaryPath) {
                rename($path, $binaryPath);
                chmod($binaryPath, 0755);

                return;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (\in_array($file->getFilename(), $possibleNames, true)) {
                rename($file->getPathname(), $binaryPath);
                chmod($binaryPath, 0755);

                return;
            }
        }
    }

    /**
     * @throws WhisperException
     */
    private function compileFromSource(): bool
    {
        $this->checkBuildDependencies();

        $tempDir = sys_get_temp_dir().'/whisper-cpp-'.uniqid();

        try {
            $this->logger->info('Cloning whisper.cpp repository');
            $process = new Process([
                'git', 'clone', '--depth', '1',
                'https://github.com/ggerganov/whisper.cpp.git',
                $tempDir,
            ]);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                $error = trim($process->getErrorOutput());
                $this->logger->error('Failed to clone whisper.cpp repository', ['error' => $error]);

                throw new WhisperException(
                    'Failed to clone whisper.cpp repository',
                    $error ?: 'Git clone failed. Install git: sudo apt install git'
                );
            }

            $gpuType = $this->platform->getAvailableGpuType();
            $this->logger->info('Compiling whisper.cpp', ['gpu_type' => $gpuType ?? 'cpu']);

            // Build with CMake for better GPU support
            $buildDir = "{$tempDir}/build";
            mkdir($buildDir, 0755, true);

            $cmakeArgs = ['cmake', '..'];

            // Add GPU-specific flags only if compiler is available
            if ($gpuType === 'cuda') {
                $this->logger->info('Compiling with CUDA support for NVIDIA GPU');
                $cmakeArgs[] = '-DGGML_CUDA=ON';
            } elseif ($gpuType === 'rocm') {
                $this->logger->info('Compiling with ROCm/HIP support for AMD GPU');
                $cmakeArgs[] = '-DGGML_HIP=ON';
            } elseif ($gpuType === 'metal') {
                $this->logger->info('Compiling with Metal support for Apple GPU');
                $cmakeArgs[] = '-DGGML_METAL=ON';
            } else {
                $this->logger->info('Compiling for CPU only (no GPU toolkit found)');
            }

            $cmakeProcess = new Process($cmakeArgs);
            $cmakeProcess->setWorkingDirectory($buildDir);
            $cmakeProcess->setTimeout(300);
            $cmakeProcess->run();

            if (! $cmakeProcess->isSuccessful()) {
                $error = trim($cmakeProcess->getErrorOutput());
                $this->logger->error('CMake configuration failed', ['error' => $error]);

                throw new WhisperException(
                    'Failed to configure whisper.cpp build',
                    $this->getBuildErrorMessage($error)
                );
            }

            $makeProcess = new Process(['cmake', '--build', '.', '--config', 'Release', '-j']);
            $makeProcess->setWorkingDirectory($buildDir);
            $makeProcess->setTimeout(900);
            $makeProcess->run();

            if (! $makeProcess->isSuccessful()) {
                $error = trim($makeProcess->getErrorOutput());
                $this->logger->error('Failed to compile whisper.cpp', ['error' => $error]);

                throw new WhisperException(
                    'Failed to compile whisper.cpp',
                    $this->getBuildErrorMessage($error)
                );
            }

            $possibleBinaries = [
                'whisper-cli' => [
                    "{$tempDir}/build/bin/whisper-cli",
                    "{$tempDir}/whisper-cli",
                ],
                'main' => [
                    "{$tempDir}/build/bin/main",
                    "{$tempDir}/main",
                    "{$tempDir}/build/main",
                ],
            ];

            $binDir = dirname($this->paths->getBinaryPath());
            $libDir = "{$binDir}/../lib";

            if (! is_dir($libDir)) {
                mkdir($libDir, 0755, true);
            }

            $this->copySharedLibraries($tempDir, $libDir);

            foreach ($possibleBinaries as $targetName => $sources) {
                foreach ($sources as $sourceBinary) {
                    if (file_exists($sourceBinary)) {
                        $targetPath = "{$binDir}/{$targetName}";
                        copy($sourceBinary, $targetPath);
                        chmod($targetPath, 0755);
                        $this->logger->info("Whisper binary '{$targetName}' compiled and installed", [
                            'source' => $sourceBinary,
                            'destination' => $targetPath,
                        ]);
                        break;
                    }
                }
            }

            $this->fixLibrarySymlinks();

            if (file_exists($this->paths->getBinaryPath())) {
                return true;
            }

            throw new WhisperException(
                'Whisper binary not found after compilation',
                'Compilation completed but binary not found in expected locations. Check logs for details.'
            );
        } finally {
            if (is_dir($tempDir)) {
                $process = new Process(['rm', '-rf', $tempDir]);
                $process->run();
            }
        }
    }

    /**
     * @throws WhisperException
     */
    private function checkBuildDependencies(): void
    {
        $missing = [];

        $commands = [
            'git' => 'git --version',
            'cmake' => 'cmake --version',
            'make' => 'make --version',
        ];

        foreach ($commands as $tool => $command) {
            $process = new Process(explode(' ', $command));
            $process->run();
            if (! $process->isSuccessful()) {
                $missing[] = $tool;
            }
        }

        if (! empty($missing)) {
            $tools = implode(', ', $missing);
            $installCmd = $this->getInstallCommand($missing);

            throw new WhisperException(
                'Missing build dependencies',
                "Required tools not found: {$tools}. Install with: {$installCmd}"
            );
        }

        // Check GPU-specific dependencies
        $gpuType = $this->platform->getGpuType();
        if ($gpuType === 'cuda') {
            $this->checkCudaDependencies();
        } elseif ($gpuType === 'rocm') {
            $this->checkRocmDependencies();
        }
    }

    private function checkCudaDependencies(): void
    {
        if (! $this->platform->hasCudaToolkit()) {
            $this->logger->warning('CUDA toolkit (nvcc) not found. GPU acceleration may not work.', [
                'hint' => 'Install CUDA toolkit: https://developer.nvidia.com/cuda-downloads',
            ]);
        }
    }

    private function checkRocmDependencies(): void
    {
        if (! $this->platform->hasRocmToolkit()) {
            $this->logger->warning('ROCm/HIP compiler not found. GPU acceleration may not work.', [
                'hint' => 'Install ROCm: https://rocm.docs.amd.com/en/latest/deploy/linux/installer/install.html',
            ]);
        }
    }

    /**
     * @param  array<string>  $missing
     */
    private function getInstallCommand(array $missing): string
    {
        $os = $this->platform->getOS();

        return match ($os) {
            'linux' => 'sudo apt install '.implode(' ', $missing).' build-essential',
            'darwin' => 'brew install '.implode(' ', $missing),
            default => 'Install: '.implode(', ', $missing),
        };
    }

    private function getBuildErrorMessage(string $error): string
    {
        if (str_contains($error, 'cmake: No such file')) {
            $cmd = $this->getInstallCommand(['cmake']);
            return "CMake not found. Install it with: {$cmd}";
        }

        if (str_contains($error, 'make: No such file')) {
            $cmd = $this->getInstallCommand(['make']);
            return "Make not found. Install it with: {$cmd}";
        }

        if (str_contains($error, 'gcc') || str_contains($error, 'g++')) {
            $os = $this->platform->getOS();
            $cmd = $os === 'linux' ? 'sudo apt install build-essential' : 'xcode-select --install';
            return "C++ compiler not found. Install it with: {$cmd}";
        }

        return $error ?: 'Compilation failed. Install build tools: cmake, make, gcc/clang';
    }

    private function copySharedLibraries(string $sourceDir, string $libDir): void
    {
        $libPatterns = $this->platform->isMacOS()
            ? ['libwhisper*.dylib', 'libggml*.dylib']
            : ['libwhisper.so*', 'libggml*.so*'];

        $searchDirs = [
            $sourceDir,
            "{$sourceDir}/build",
            "{$sourceDir}/build/src",
            "{$sourceDir}/build/ggml/src",
            "{$sourceDir}/src",
            "{$sourceDir}/ggml/src",
        ];

        foreach ($searchDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($libPatterns as $pattern) {
                $files = glob("{$dir}/{$pattern}");
                if ($files === false) {
                    continue;
                }

                foreach ($files as $libPath) {
                    if (is_link($libPath)) {
                        continue;
                    }

                    $targetLib = "{$libDir}/".basename($libPath);
                    if (! file_exists($targetLib)) {
                        copy($libPath, $targetLib);
                        chmod($targetLib, 0755);
                        $this->logger->info('Whisper shared library installed', [
                            'source' => $libPath,
                            'destination' => $targetLib,
                        ]);
                    }
                }
            }
        }

        $this->createLibrarySymlinks($libDir);
    }

    private function createLibrarySymlinks(string $libDir): void
    {
        if ($this->platform->isWindows()) {
            return;
        }

        if ($this->platform->isMacOS()) {
            $this->createMacOSLibrarySymlinks($libDir);
            return;
        }

        $this->createLinuxLibrarySymlinks($libDir);
    }

    private function createLinuxLibrarySymlinks(string $libDir): void
    {
        $libs = glob("{$libDir}/*.so.*");
        if ($libs === false) {
            return;
        }

        foreach ($libs as $lib) {
            if (is_link($lib)) {
                continue;
            }

            $basename = basename($lib);

            if (preg_match('/^(.+\.so)\.(\d+)\./', $basename, $matches)) {
                $baseWithSo = $matches[1];
                $majorVersion = $matches[2];

                $versionedSymlink = "{$libDir}/{$baseWithSo}.{$majorVersion}";
                if (! file_exists($versionedSymlink)) {
                    @symlink($basename, $versionedSymlink);
                    $this->logger->info('Created library symlink', [
                        'symlink' => $versionedSymlink,
                        'target' => $basename,
                    ]);
                }

                $baseSymlink = "{$libDir}/{$baseWithSo}";
                if (! file_exists($baseSymlink)) {
                    @symlink($basename, $baseSymlink);
                }
            }
        }
    }

    private function createMacOSLibrarySymlinks(string $libDir): void
    {
        $libs = glob("{$libDir}/*.dylib");
        if ($libs === false) {
            return;
        }

        foreach ($libs as $lib) {
            if (is_link($lib)) {
                continue;
            }

            $basename = basename($lib);

            if (preg_match('/^(.+)\.(\d+)\.dylib$/', $basename, $matches)) {
                $baseName = $matches[1];
                $majorVersion = $matches[2];

                $versionedSymlink = "{$libDir}/{$baseName}.{$majorVersion}.dylib";
                if (! file_exists($versionedSymlink) && $versionedSymlink !== $lib) {
                    @symlink($basename, $versionedSymlink);
                }

                $baseSymlink = "{$libDir}/{$baseName}.dylib";
                if (! file_exists($baseSymlink)) {
                    @symlink($basename, $baseSymlink);
                    $this->logger->info('Created library symlink', [
                        'symlink' => $baseSymlink,
                        'target' => $basename,
                    ]);
                }
            }
        }
    }

    public function fixLibrarySymlinks(): void
    {
        $binDir = dirname($this->paths->getBinaryPath());
        $libDir = "{$binDir}/../lib";

        if (! is_dir($libDir)) {
            return;
        }

        $this->createLibrarySymlinks($libDir);
    }

    /**
     * @throws WhisperException
     */
    public function reinstallBinary(): bool
    {
        $binaryPath = $this->paths->getBinaryPath();
        $binDir = dirname($binaryPath);
        $libDir = "{$binDir}/../lib";

        foreach (glob("{$binDir}/whisper*") ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob("{$binDir}/main") ?: [] as $file) {
            @unlink($file);
        }

        if (is_dir($libDir)) {
            foreach (glob("{$libDir}/*.so*") ?: [] as $file) {
                @unlink($file);
            }
        }

        return $this->downloadBinary();
    }
}
