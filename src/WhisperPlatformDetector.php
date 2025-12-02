<?php

declare(strict_types=1);

namespace LaravelWhisper;

use Symfony\Component\Process\Process;

final class WhisperPlatformDetector
{
    public function __construct() {}

    public function getOS(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            default => 'linux',
        };
    }

    public function getArch(): string
    {
        $arch = php_uname('m');

        return match ($arch) {
            'arm64', 'aarch64' => 'arm64',
            'x86_64', 'amd64', 'AMD64' => 'x86_64',
            default => $arch,
        };
    }

    public function isWindows(): bool
    {
        return is_windows();
    }

    public function isMacOS(): bool
    {
        return is_mac();
    }

    public function isLinux(): bool
    {
        return is_linux();
    }

    public function hasGpuSupport(): bool
    {
        return match ($this->getOS()) {
            'darwin' => $this->hasMacGpu(),
            'windows' => $this->hasWindowsGpu(),
            'linux' => $this->hasLinuxGpu(),
            default => false,
        };
    }

    private function hasMacGpu(): bool
    {
        $process = new Process(['sysctl', '-n', 'machdep.cpu.brand_string']);
        $process->run();

        return $process->isSuccessful() && str_contains(strtolower($process->getOutput()), 'apple');
    }

    private function hasWindowsGpu(): bool
    {
        // Check for NVIDIA GPU
        $process = new Process(['where', 'nvidia-smi']);
        $process->run();

        if ($process->isSuccessful()) {
            $nvidiaCheck = new Process(['nvidia-smi', '-L']);
            $nvidiaCheck->run();

            if ($nvidiaCheck->isSuccessful()) {
                return true;
            }
        }

        // Check for AMD GPU via Windows Management Instrumentation
        $wmiProcess = new Process([
            'powershell',
            '-Command',
            'Get-WmiObject Win32_VideoController | Where-Object { $_.Name -like "*AMD*" -or $_.Name -like "*Radeon*" } | Select-Object -First 1'
        ]);
        $wmiProcess->run();

        if ($wmiProcess->isSuccessful() && trim($wmiProcess->getOutput()) !== '') {
            return true;
        }

        return false;
    }

    private function hasLinuxGpu(): bool
    {
        // Check for NVIDIA GPU
        $process = new Process(['which', 'nvidia-smi']);
        $process->run();

        if ($process->isSuccessful()) {
            $nvidiaCheck = new Process(['nvidia-smi', '-L']);
            $nvidiaCheck->run();

            if ($nvidiaCheck->isSuccessful()) {
                return true;
            }
        }

        // Check for AMD GPU (ROCm)
        $rocmProcess = new Process(['which', 'rocm-smi']);
        $rocmProcess->run();

        if ($rocmProcess->isSuccessful()) {
            $amdCheck = new Process(['rocm-smi', '--showproductname']);
            $amdCheck->run();

            if ($amdCheck->isSuccessful()) {
                return true;
            }
        }

        // Fallback: Check for AMD GPU via /dev/kfd (Kernel Fusion Driver)
        if (file_exists('/dev/kfd')) {
            return true;
        }

        return false;
    }
}
