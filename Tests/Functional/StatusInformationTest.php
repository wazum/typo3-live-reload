<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Adminpanel\ModuleApi\ModuleData;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\AdminPanel\StatusInformation;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Middleware\PollEndpointMiddleware;
use Wazum\LiveReload\Resolver\DevServerUrlResolver;
use Wazum\LiveReload\Tests\Support\SwitchesApplicationContext;

final class StatusInformationTest extends FunctionalTestCase
{
    use SwitchesApplicationContext;

    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'live_reload' => ['activeContexts' => 'Development, Testing'],
        ],
    ];

    protected function tearDown(): void
    {
        $this->restoreApplicationContext();
        parent::tearDown();
    }

    #[Test]
    public function reportsPollTransportWithEndpointAndIntervalOutsideDevelopment(): void
    {
        $data = $this->statusInformation()->getDataToStore(new ServerRequest('https://example.org/', 'GET'));

        $stored = $data->getArrayCopy();
        self::assertSame('poll', $stored['transport']);
        self::assertSame(PollEndpointMiddleware::PATH, $stored['pollEndpoint']);
        self::assertSame(3000, $stored['pollInterval']);
    }

    #[Test]
    public function reportsViteTransportInDevelopmentContextWithAResolvedUrl(): void
    {
        $this->switchApplicationContext('Development');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['viteServerPublicUrl'] = 'http://localhost:5173';

        $data = $this->statusInformation()->getDataToStore(new ServerRequest('https://example.org/', 'GET'));

        self::assertSame('vite', $data->getArrayCopy()['transport']);
    }

    #[Test]
    public function reportsPollTransportInDevelopmentWhenNoDevServerUrlResolves(): void
    {
        $this->switchApplicationContext('Development');

        $data = $this->statusInformation()->getDataToStore(new ServerRequest('https://example.org/', 'GET'));

        self::assertSame('poll', $data->getArrayCopy()['transport']);
    }

    #[Test]
    public function rendersEndpointAndIntervalForThePollTransport(): void
    {
        $statusInformation = $this->statusInformation();
        $data = $statusInformation->getDataToStore(new ServerRequest('https://example.org/', 'GET'));

        $content = $statusInformation->getContent(new ModuleData($data->getArrayCopy()));

        self::assertStringContainsString('<td>poll — /__live-reload/poll, every 3000 ms</td>', $content);
    }

    #[Test]
    public function rendersTheViteTransportWithoutPollDetails(): void
    {
        $this->switchApplicationContext('Development');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['viteServerPublicUrl'] = 'http://localhost:5173';
        $statusInformation = $this->statusInformation();
        $data = $statusInformation->getDataToStore(new ServerRequest('https://example.org/', 'GET'));

        $content = $statusInformation->getContent(new ModuleData($data->getArrayCopy()));

        self::assertStringContainsString('<td>vite</td>', $content);
        self::assertStringNotContainsString('/__live-reload/poll', $content);
    }

    private function statusInformation(): StatusInformation
    {
        return new StatusInformation(
            $this->get(ExtensionSettings::class),
            $this->get(DevServerUrlResolver::class),
            $this->get(Features::class),
            $this->get(ViewFactoryInterface::class),
        );
    }
}
