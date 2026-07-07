<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Collector\RenderedFileCollector;
use Wazum\LiveReload\Tests\Support\SwitchesApplicationContext;

final class CapturingViewFactoryTest extends FunctionalTestCase
{
    use SwitchesApplicationContext;

    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected function tearDown(): void
    {
        $this->restoreApplicationContext();
        parent::tearDown();
    }

    #[Test]
    public function capturesTemplateAndViewHelperFilesInDevelopmentContext(): void
    {
        $this->switchApplicationContext('Development');

        $view = $this->createFixtureView();
        self::assertInstanceOf(FluidViewAdapter::class, $view);
        $view->render();

        $tags = $this->get(RenderedFileCollector::class)->fileTags($this->extensionPath());
        self::assertContains('file:Tests/Functional/Fixtures/Templates/Page.html', $tags);
        self::assertContains('file:Tests/Functional/Fixtures/RecordableViewHelper.php', $tags);
    }

    #[Test]
    public function capturesNothingOutsideDevelopmentContext(): void
    {
        $this->switchApplicationContext('Testing');

        $this->createFixtureView()->render();

        self::assertSame([], $this->get(RenderedFileCollector::class)->fileTags($this->extensionPath()));
    }

    #[Test]
    public function capturesNothingWhenDevelopmentIsNotAnActiveContext(): void
    {
        $this->switchApplicationContext('Development');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['activeContexts'] = 'Production/Staging';

        $this->createFixtureView()->render();

        self::assertSame([], $this->get(RenderedFileCollector::class)->fileTags($this->extensionPath()));
    }

    #[Test]
    public function capturesEvenWhenTheCompileCacheIsWarm(): void
    {
        $this->switchApplicationContext('Testing');
        $this->createFixtureView()->render();

        $this->switchApplicationContext('Development');
        $this->createFixtureView()->render();

        $tags = $this->get(RenderedFileCollector::class)->fileTags($this->extensionPath());
        self::assertContains('file:Tests/Functional/Fixtures/Templates/Page.html', $tags);
    }

    private function createFixtureView(): \TYPO3\CMS\Core\View\ViewInterface
    {
        $fixtures = __DIR__ . '/Fixtures/Templates';

        return $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [$fixtures],
            templatePathAndFilename: $fixtures . '/Page.html',
        ));
    }

    private function extensionPath(): string
    {
        return (string)realpath(__DIR__ . '/../..');
    }
}
