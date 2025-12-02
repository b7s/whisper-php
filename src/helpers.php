<?php

declare(strict_types=1);

if (! function_exists('is_windows')) {
    function is_windows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (! function_exists('is_mac')) {
    function is_mac(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }
}

if (! function_exists('is_linux')) {
    function is_linux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }
}
