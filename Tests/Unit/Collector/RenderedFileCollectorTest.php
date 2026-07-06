<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Collector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wazum\LiveReload\Collector\RenderedFileCollector;

final class RenderedFileCollectorTest extends TestCase
{
    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectPath = (string)realpath(sys_get_temp_dir()) . '/rendered-file-collector-' . bin2hex(random_bytes(4));
        mkdir($this->projectPath . '/local/site/Resources', 0775, true);
        mkdir($this->projectPath . '/vendor/wazum', 0775, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->projectPath));
        parent::tearDown();
    }

    #[Test]
    public function normalizesRecordedPathsToProjectRelativeFileTags(): void
    {
        $file = $this->projectPath . '/local/site/Resources/Partial.html';
        touch($file);

        $collector = $this->createCollector();
        $collector->add($file);

        self::assertSame(['file:local/site/Resources/Partial.html'], $collector->fileTags($this->projectPath));
    }

    #[Test]
    public function resolvesSymlinkedPathsToTheirRealLocation(): void
    {
        $file = $this->projectPath . '/local/site/Resources/Partial.html';
        touch($file);
        symlink($this->projectPath . '/local/site', $this->projectPath . '/vendor/wazum/site');

        $collector = $this->createCollector();
        $collector->add($this->projectPath . '/vendor/wazum/site/Resources/Partial.html');

        self::assertSame(['file:local/site/Resources/Partial.html'], $collector->fileTags($this->projectPath));
    }

    #[Test]
    public function dropsFilesUnderTheVendorDirectoryAndOutsideTheProject(): void
    {
        $vendorFile = $this->projectPath . '/vendor/wazum/Library.php';
        touch($vendorFile);
        $outsideFile = tempnam(sys_get_temp_dir(), 'outside-');

        $collector = $this->createCollector();
        $collector->add($vendorFile);
        $collector->add((string)$outsideFile);
        $collector->add($this->projectPath . '/local/site/Resources/Missing.html');
        $collector->add('');

        self::assertSame([], $collector->fileTags($this->projectPath));

        unlink((string)$outsideFile);
    }

    #[Test]
    public function respectsACustomVendorDirectoryName(): void
    {
        mkdir($this->projectPath . '/third-party/lib', 0775, true);
        $customVendorFile = $this->projectPath . '/third-party/lib/Library.php';
        touch($customVendorFile);
        $projectFile = $this->projectPath . '/local/site/Resources/Partial.html';
        touch($projectFile);

        $collector = new RenderedFileCollector($this->projectPath . '/third-party');
        $collector->add($customVendorFile);
        $collector->add($projectFile);

        self::assertSame(['file:local/site/Resources/Partial.html'], $collector->fileTags($this->projectPath));
    }

    #[Test]
    public function detectsTheRealVendorDirectoryFromComposerByDefault(): void
    {
        $realVendorFile = (string)(new ReflectionClass(\Composer\InstalledVersions::class))->getFileName();
        $realProjectPath = dirname($realVendorFile, 3);

        $collector = new RenderedFileCollector();
        $collector->add($realVendorFile);

        self::assertSame([], $collector->fileTags($realProjectPath));
    }

    #[Test]
    public function keepsUmlautsAndSpacesInFileTags(): void
    {
        mkdir($this->projectPath . '/local/site/Übersicht Ordner', 0775, true);
        $file = $this->projectPath . '/local/site/Übersicht Ordner/Kopfzeile Größe.html';
        touch($file);

        $collector = $this->createCollector();
        $collector->add($file);

        self::assertSame(
            ['file:local/site/Übersicht Ordner/Kopfzeile Größe.html'],
            $collector->fileTags($this->projectPath),
        );
    }

    #[Test]
    public function deduplicatesAndSortsTags(): void
    {
        $first = $this->projectPath . '/local/site/Resources/B.html';
        $second = $this->projectPath . '/local/site/Resources/A.html';
        touch($first);
        touch($second);

        $collector = $this->createCollector();
        $collector->add($first);
        $collector->add($second);
        $collector->add($first);

        self::assertSame(
            ['file:local/site/Resources/A.html', 'file:local/site/Resources/B.html'],
            $collector->fileTags($this->projectPath),
        );
    }

    private function createCollector(): RenderedFileCollector
    {
        return new RenderedFileCollector($this->projectPath . '/vendor');
    }
}
