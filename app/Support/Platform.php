<?php

namespace App\Support;

/**
 * The operating system Larakey is running on. Injected rather than read from PHP_OS_FAMILY
 * directly so platform-specific behaviour can be tested on any machine.
 */
class Platform
{
    public function __construct(public readonly string $family = PHP_OS_FAMILY) {}

    public function isLinux(): bool
    {
        return $this->family === 'Linux';
    }

    public function isMac(): bool
    {
        return $this->family === 'Darwin';
    }
}
