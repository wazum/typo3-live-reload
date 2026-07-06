<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Fluid;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use Wazum\LiveReload\Collector\RenderedFileCollector;
use Wazum\LiveReload\Fluid\CapturingViewHelperResolver;
use Wazum\LiveReload\Tests\Unit\Fluid\Fixtures\RecordableViewHelper;

final class CapturingViewHelperResolverTest extends TestCase
{
    #[Test]
    public function recordsTheViewHelperClassFileAndDelegatesInstantiation(): void
    {
        $collector = new RenderedFileCollector();
        $resolver = CapturingViewHelperResolver::fromExisting($this->createResolver(), $collector);

        $instance = $resolver->createViewHelperInstanceFromClassName(RecordableViewHelper::class);

        self::assertInstanceOf(RecordableViewHelper::class, $instance);
        $extensionPath = (string)realpath(__DIR__ . '/../../..');
        self::assertSame(
            ['file:Tests/Unit/Fluid/Fixtures/RecordableViewHelper.php'],
            $collector->fileTags($extensionPath),
        );
    }

    #[Test]
    public function scopedCopiesKeepRecording(): void
    {
        $collector = new RenderedFileCollector();
        $resolver = CapturingViewHelperResolver::fromExisting($this->createResolver(), $collector);

        $copy = $resolver->getScopedCopy();
        $copy->createViewHelperInstanceFromClassName(RecordableViewHelper::class);

        $extensionPath = (string)realpath(__DIR__ . '/../../..');
        self::assertSame(
            ['file:Tests/Unit/Fluid/Fixtures/RecordableViewHelper.php'],
            $collector->fileTags($extensionPath),
        );
    }

    #[Test]
    public function copiesNamespaceConfigurationFromTheSourceResolver(): void
    {
        $source = $this->createResolver();
        $source->addNamespace('demo', 'Wazum\\LiveReload\\Tests\\Unit\\Fluid\\Fixtures');

        $resolver = CapturingViewHelperResolver::fromExisting($source, new RenderedFileCollector());

        self::assertSame($source->getNamespaces(), $resolver->getNamespaces());
    }

    private function createResolver(): ViewHelperResolver
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new RuntimeException('not registered', 1751800000);
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        return new ViewHelperResolver($container, []);
    }
}
