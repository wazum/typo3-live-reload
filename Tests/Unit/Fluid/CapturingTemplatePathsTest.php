<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Fluid;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use Wazum\LiveReload\Collector\RenderedFileCollector;
use Wazum\LiveReload\Fluid\CapturingTemplatePaths;

final class CapturingTemplatePathsTest extends TestCase
{
    #[Test]
    public function recordsResolvedTemplatePartialAndLayoutFiles(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        $collector = new RenderedFileCollector();

        $source = new TemplatePaths();
        $source->setTemplateRootPaths([$fixtures . '/Templates']);
        $source->setPartialRootPaths([$fixtures . '/Partials']);
        $source->setLayoutRootPaths([$fixtures . '/Layouts']);
        $source->setFormat('html');

        $paths = CapturingTemplatePaths::fromExisting($source, $collector);
        $paths->getTemplateSource('Default', 'Simple');
        $paths->getPartialSource('Box');
        $paths->getLayoutSource('Default');

        $tags = $collector->fileTags((string)realpath(__DIR__));
        self::assertSame(
            ['file:Fixtures/Layouts/Default.html', 'file:Fixtures/Partials/Box.html', 'file:Fixtures/Templates/Default/Simple.html'],
            $tags,
        );
    }

    #[Test]
    public function copiesConfigurationFromTheSourcePaths(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        $source = new TemplatePaths();
        $source->setTemplateRootPaths([$fixtures . '/Templates']);
        $source->setFormat('html');

        $paths = CapturingTemplatePaths::fromExisting($source, new RenderedFileCollector());

        self::assertSame($source->getTemplateRootPaths(), $paths->getTemplateRootPaths());
        self::assertSame('html', $paths->getFormat());
    }

    #[Test]
    public function inlineTemplateSourceRecordsNoFile(): void
    {
        $collector = new RenderedFileCollector();
        $paths = CapturingTemplatePaths::fromExisting(new TemplatePaths(), $collector);
        $paths->setTemplateSource('<h1>inline</h1>');

        self::assertSame('<h1>inline</h1>', $paths->getTemplateSource());
        self::assertSame([], $collector->fileTags('/'));
    }

    #[Test]
    public function compileIdentifiersDifferFromUninstrumentedOnes(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        $source = new TemplatePaths();
        $source->setTemplateRootPaths([$fixtures . '/Templates']);
        $source->setPartialRootPaths([$fixtures . '/Partials']);
        $source->setLayoutRootPaths([$fixtures . '/Layouts']);
        $source->setFormat('html');

        $paths = CapturingTemplatePaths::fromExisting($source, new RenderedFileCollector());

        self::assertNotSame(
            $source->getTemplateIdentifier('Default', 'Simple'),
            $paths->getTemplateIdentifier('Default', 'Simple'),
        );
        self::assertNotSame($source->getPartialIdentifier('Box'), $paths->getPartialIdentifier('Box'));
        self::assertNotSame($source->getLayoutIdentifier('Default'), $paths->getLayoutIdentifier('Default'));
    }

    #[Test]
    public function unresolvableSourcesAreSwallowed(): void
    {
        $collector = new RenderedFileCollector();
        $paths = CapturingTemplatePaths::fromExisting(new TemplatePaths(), $collector);

        try {
            $paths->getPartialSource('DoesNotExist');
        } catch (Throwable) {
            // The base implementation may throw for unresolvable partials; capture must not add to that.
        }

        self::assertSame([], $collector->fileTags('/'));
    }
}
