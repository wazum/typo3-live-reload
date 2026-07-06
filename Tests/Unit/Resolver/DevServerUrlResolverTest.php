<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Resolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Resolver\DevServerUrlResolver;

final class DevServerUrlResolverTest extends TestCase
{
    #[Test]
    public function returnsConfiguredPublicUrl(): void
    {
        $resolver = new DevServerUrlResolver($this->settingsWith('https://vite.example:5173/'));

        self::assertSame(
            'https://vite.example:5173',
            $resolver->resolve(new ServerRequest('https://example.org/page', 'GET')),
        );
    }

    #[Test]
    public function returnsNullWithoutConfigurationAndWithoutViteAssetCollector(): void
    {
        $resolver = new DevServerUrlResolver($this->settingsWith(''));

        self::assertNull($resolver->resolve(new ServerRequest('https://example.org/page', 'GET')));
    }

    #[Test]
    public function explainsEachResolutionOutcome(): void
    {
        $request = new ServerRequest('https://example.org/page', 'GET');

        self::assertSame(
            ['url' => 'https://vite.example:5173', 'source' => 'setting'],
            (new DevServerUrlResolver($this->settingsWith('https://vite.example:5173/')))->explain($request),
        );
        self::assertSame(
            ['url' => null, 'source' => 'vite-asset-collector not installed'],
            (new DevServerUrlResolver($this->settingsWith('')))->explain($request),
        );

        $disabled = new class {
            public function useDevServer(): bool
            {
                return false;
            }
        };
        self::assertSame(
            ['url' => null, 'source' => 'vite-asset-collector dev server disabled'],
            (new DevServerUrlResolver($this->settingsWith(''), $disabled))->explain($request),
        );

        $active = new class {
            public function useDevServer(): bool
            {
                return true;
            }

            public function determineDevServer(ServerRequestInterface $request): Uri
            {
                return new Uri('http://vite.example:5173/');
            }
        };
        self::assertSame(
            ['url' => 'http://vite.example:5173', 'source' => 'vite-asset-collector'],
            (new DevServerUrlResolver($this->settingsWith(''), $active))->explain($request),
        );
    }

    #[Test]
    public function returnsNullWhenDevServerIsDisabled(): void
    {
        $viteService = new class {
            public function useDevServer(): bool
            {
                return false;
            }

            public function determineDevServer(ServerRequestInterface $request): Uri
            {
                return new Uri('http://vite.example:5173/');
            }
        };
        $resolver = new DevServerUrlResolver($this->settingsWith(''), $viteService);

        self::assertNull($resolver->resolve(new ServerRequest('https://example.org/page', 'GET')));
    }

    #[Test]
    public function returnsTrimmedDevServerUriFromViteAssetCollector(): void
    {
        $viteService = new class {
            public function useDevServer(): bool
            {
                return true;
            }

            public function determineDevServer(ServerRequestInterface $request): Uri
            {
                return new Uri('http://vite.example:5173/');
            }
        };
        $resolver = new DevServerUrlResolver($this->settingsWith(''), $viteService);

        self::assertSame('http://vite.example:5173', $resolver->resolve(new ServerRequest('https://example.org/page', 'GET')));
    }

    #[Test]
    public function returnsNullWhenViteAssetCollectorThrows(): void
    {
        $viteService = new class {
            public function useDevServer(): bool
            {
                throw new RuntimeException('container failure');
            }
        };
        $resolver = new DevServerUrlResolver($this->settingsWith(''), $viteService);

        self::assertNull($resolver->resolve(new ServerRequest('https://example.org/page', 'GET')));
    }

    private function settingsWith(string $publicUrl): ExtensionSettings
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn(['viteServerPublicUrl' => $publicUrl]);

        return new ExtensionSettings($extensionConfiguration);
    }
}
