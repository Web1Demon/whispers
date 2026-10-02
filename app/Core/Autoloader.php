<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 compliant lightweight autoloader
 */
class Autoloader
{
    private string $prefix;
    private string $baseDir;

    public function __construct(string $prefix, string $baseDir)
    {
        $this->prefix = trim($prefix, '\\') . '\\';
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function loadClass(string $class): bool
    {
        $len = strlen($this->prefix);
        if (strncmp($this->prefix, $class, $len) !== 0) {
            return false;
        }

        $relativeClass = substr($class, $len);
        $file = $this->baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return true;
        }

        return false;
    }
}
