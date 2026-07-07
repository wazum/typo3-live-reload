<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Broadcaster;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use Wazum\LiveReload\Broadcaster\ViteDevServerBroadcaster;
use Wazum\LiveReload\Configuration\ExtensionSettings;

final class ViteDevServerBroadcasterTest extends TestCase
{
    #[Test]
    public function postsTagsAsJsonToTheConfiguredEndpoint(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())
            ->method('request')
            ->with(
                'http://vite:5174/__typo3-live-reload',
                'POST',
                self::callback(static function (array $options): bool {
                    return $options['json'] === ['tags' => ['tt_content_5', 'pageId_42']]
                        && $options['timeout'] === 0.5;
                }),
            );

        $this->broadcaster($requestFactory)->broadcast('tt_content_5', 'pageId_42');
    }

    #[Test]
    public function sendsTheSharedSecretHeaderWhenConfigured(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())
            ->method('request')
            ->with(
                self::anything(),
                'POST',
                self::callback(static function (array $options): bool {
                    return ($options['headers']['X-Live-Reload-Secret'] ?? null) === 'wonderful-secret';
                }),
            );

        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'viteServerInternalUrl' => 'http://vite:5174',
            'viteSharedSecret' => 'wonderful-secret',
        ]);
        $broadcaster = new ViteDevServerBroadcaster(new ExtensionSettings($extensionConfiguration), $requestFactory);

        $broadcaster->broadcast('pageId_1');
    }

    #[Test]
    public function sendsNoSecretHeaderByDefault(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())
            ->method('request')
            ->with(
                self::anything(),
                'POST',
                self::callback(static function (array $options): bool {
                    return !isset($options['headers']['X-Live-Reload-Secret']);
                }),
            );

        $this->broadcaster($requestFactory)->broadcast('pageId_1');
    }

    #[Test]
    public function doesNothingWithoutTags(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::never())->method('request');

        $this->broadcaster($requestFactory)->broadcast();
    }

    #[Test]
    public function swallowsTransportErrors(): void
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willThrowException(new RuntimeException('connection refused'));

        $this->broadcaster($requestFactory)->broadcast('pageId_1');
        $this->addToAssertionCount(1);
    }

    private function broadcaster(RequestFactory $requestFactory): ViteDevServerBroadcaster
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn(['viteServerInternalUrl' => 'http://vite:5174']);

        return new ViteDevServerBroadcaster(new ExtensionSettings($extensionConfiguration), $requestFactory);
    }
}
