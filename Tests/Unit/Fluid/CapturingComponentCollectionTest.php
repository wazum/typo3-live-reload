<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Fluid;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use Wazum\LiveReload\Collector\RenderedFileCollector;
use Wazum\LiveReload\Fluid\CapturingComponentCollection;
use Wazum\LiveReload\Fluid\CapturingViewHelperResolver;
use Wazum\LiveReload\Tests\Unit\Fluid\Fixtures\TestComponentCollection;

final class CapturingComponentCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(ComponentTemplateResolverInterface::class)) {
            self::markTestSkipped('Fluid components require typo3fluid/fluid >= 4');
        }
    }

    #[Test]
    public function resolverWrapsComponentCollectionsAndCachesTheWrapper(): void
    {
        $resolver = CapturingViewHelperResolver::fromExisting($this->createResolver(), new RenderedFileCollector());

        $delegate = $resolver->getResolverDelegate(TestComponentCollection::class);

        self::assertInstanceOf(CapturingComponentCollection::class, $delegate);
        self::assertSame($delegate, $resolver->getResolverDelegate(TestComponentCollection::class));
    }

    #[Test]
    public function renderingAComponentRecordsItsTemplateFile(): void
    {
        $collector = new RenderedFileCollector();
        $resolver = CapturingViewHelperResolver::fromExisting($this->createResolver(), $collector);

        $delegate = $resolver->getResolverDelegate(TestComponentCollection::class);
        self::assertInstanceOf(CapturingComponentCollection::class, $delegate);
        $output = $delegate->getComponentRenderer()->renderComponent('badge', [], [], new RenderingContext());

        self::assertStringContainsString('class="badge"', $output);
        $extensionPath = (string)realpath(__DIR__ . '/../../..');
        self::assertContains(
            'file:Tests/Unit/Fluid/Fixtures/Components/Badge/Badge.html',
            $collector->fileTags($extensionPath),
        );
    }

    #[Test]
    public function delegatesComponentMetadataToTheInnerCollection(): void
    {
        $resolver = CapturingViewHelperResolver::fromExisting($this->createResolver(), new RenderedFileCollector());
        $inner = new TestComponentCollection();

        $delegate = $resolver->getResolverDelegate(TestComponentCollection::class);
        self::assertInstanceOf(CapturingComponentCollection::class, $delegate);

        self::assertSame($inner->getNamespace(), $delegate->getNamespace());
        self::assertSame($inner->resolveTemplateName('badge'), $delegate->resolveTemplateName('badge'));
        self::assertSame($inner->resolveViewHelperClassName('badge'), $delegate->resolveViewHelperClassName('badge'));
        self::assertEquals($inner->getComponentDefinition('badge'), $delegate->getComponentDefinition('badge'));
        self::assertSame($inner->getAdditionalVariables('badge'), $delegate->getAdditionalVariables('badge'));
    }

    #[Test]
    public function leavesNonComponentDelegatesUntouched(): void
    {
        $resolver = CapturingViewHelperResolver::fromExisting($this->createResolver(), new RenderedFileCollector());

        $delegate = $resolver->getResolverDelegate(\TYPO3Fluid\Fluid\ViewHelpers::class);

        self::assertNotInstanceOf(CapturingComponentCollection::class, $delegate);
    }

    private function createResolver(): ViewHelperResolver
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new RuntimeException('not registered', 1751800001);
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        return new ViewHelperResolver($container, []);
    }
}
