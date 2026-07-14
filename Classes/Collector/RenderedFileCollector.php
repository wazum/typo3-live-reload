<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Collector;

use Composer\InstalledVersions;
use ReflectionClass;
use TYPO3\CMS\Core\SingletonInterface;

final class RenderedFileCollector implements SingletonInterface
{
    /**
     * @var array<string, true>
     */
    private array $paths = [];

    private readonly string $vendorPath;

    public function __construct(?string $vendorPath = null)
    {
        $this->vendorPath = rtrim($vendorPath ?? self::detectVendorPath(), '/') . '/';
    }

    public function add(string $absolutePath): void
    {
        if ($absolutePath === '') {
            return;
        }
        $this->paths[$absolutePath] = true;
    }

    /**
     * @return array<string>
     */
    public function fileTags(string $projectPath): array
    {
        // Recorded files are realpath'd below, so the prefix must be
        // resolved too or a symlinked project path never matches.
        $resolvedProjectPath = realpath($projectPath);
        $prefix = rtrim($resolvedProjectPath !== false ? $resolvedProjectPath : $projectPath, '/') . '/';
        $tags = [];
        foreach (array_keys($this->paths) as $path) {
            $resolved = realpath($path);
            if ($resolved === false || !str_starts_with($resolved, $prefix) || str_starts_with($resolved, $this->vendorPath)) {
                continue;
            }
            $tags['file:' . substr($resolved, strlen($prefix))] = true;
        }
        $result = array_keys($tags);
        sort($result);

        return $result;
    }

    /**
     * The composer vendor directory is configurable (vendor-dir); the one
     * reliable anchor is the location InstalledVersions was loaded from:
     * <vendor-dir>/composer/InstalledVersions.php.
     */
    private static function detectVendorPath(): string
    {
        $file = (new ReflectionClass(InstalledVersions::class))->getFileName();
        $vendorDir = is_string($file) ? dirname($file, 2) : '';
        $resolved = $vendorDir !== '' ? realpath($vendorDir) : false;

        return $resolved !== false ? $resolved : $vendorDir;
    }
}
