<?php

declare(strict_types=1);

namespace WhisperPHP;

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

    /**
     * Check if GPU support is available (GPU detected AND toolkit installed).
     */
    public function hasGpuSupport(): bool
    {
        return $this->getAvailableGpuType() !== null;
    }

    /**
     * Get the GPU type: 'cuda' (NVIDIA), 'rocm' (AMD), 'metal' (Apple), or null.
     * Only returns a type if the GPU is detected (hardware present).
     */
    public function getGpuType(): ?string
    {
        $os = $this->getOS();

        if ($os === 'darwin') {
            return $this->hasMacGpu() ? 'metal' : null;
        }

        if ($this->hasNvidiaGpu()) {
            return 'cuda';
        }

        if ($this->hasAmdGpu()) {
            return 'rocm';
        }

        return null;
    }

    /**
     * Get GPU type only if the required toolkit/compiler is available.
     * Returns null if GPU is detected but toolkit is missing.
     */
    public function getAvailableGpuType(): ?string
    {
        $gpuType = $this->getGpuType();

        if ($gpuType === 'cuda' && ! $this->hasCudaToolkit()) {
            return null;
        }

        if ($gpuType === 'rocm' && ! $this->hasRocmToolkit()) {
            return null;
        }

        return $gpuType;
    }

    public function hasCudaToolkit(): bool
    {
        $which = $this->isWindows() ? 'where' : 'which';
        $process = new Process([$which, 'nvcc']);
        $process->run();
        return $process->isSuccessful();
    }

    public function hasRocmToolkit(): bool
    {
        $process = new Process(['which', 'hipcc']);
        $process->run();
        return $process->isSuccessful();
    }

    public function hasNvidiaGpu(): bool
    {
        $which = $this->isWindows() ? 'where' : 'which';
        $process = new Process([$which, 'nvidia-smi']);
        $process->run();

        if ($process->isSuccessful()) {
            $nvidiaCheck = new Process(['nvidia-smi', '-L']);
            $nvidiaCheck->run();
            return $nvidiaCheck->isSuccessful();
        }

        return false;
    }

    public function hasAmdGpu(): bool
    {
        // Check for ROCm
        $which = $this->isWindows() ? 'where' : 'which';
        $process = new Process([$which, 'rocm-smi']);
        $process->run();

        if ($process->isSuccessful()) {
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

    private function hasMacGpu(): bool
    {
        $process = new Process(['sysctl', '-n', 'machdep.cpu.brand_string']);
        $process->run();

        return $process->isSuccessful() && str_contains(strtolower($process->getOutput()), 'apple');
    }

    private function hasWindowsGpu(): bool
    {
        return $this->hasNvidiaGpu() || $this->hasAmdGpuWindows();
    }

    private function hasLinuxGpu(): bool
    {
        return $this->hasNvidiaGpu() || $this->hasAmdGpu();
    }

    private function hasAmdGpuWindows(): bool
    {
        $wmiProcess = new Process([
            'powershell',
            '-Command',
            'Get-WmiObject Win32_VideoController | Where-Object { $_.Name -like "*AMD*" -or $_.Name -like "*Radeon*" } | Select-Object -First 1',
        ]);
        $wmiProcess->run();

        return $wmiProcess->isSuccessful() && trim($wmiProcess->getOutput()) !== '';
    }
}
