<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Wazum\ContentLiveReload\Configuration\ExtensionSettings;

final class ExtensionSettingsTest extends TestCase
{
    #[Test]
    public function returnsDefaultsWhenConfigurationIsEmpty(): void
    {
        $settings = $this->settingsWith([]);

        self::assertSame(['Development'], $settings->activeContexts());
        self::assertSame('tagged', $settings->reloadMode());
        self::assertSame('http://localhost:5173', $settings->viteServerInternalUrl());
        self::assertSame('', $settings->viteServerPublicUrl());
    }

    #[Test]
    public function parsesConfiguredValues(): void
    {
        $settings = $this->settingsWith([
            'activeContexts' => 'Development, Testing/Local',
            'reloadMode' => 'always',
            'viteServerInternalUrl' => 'http://vite:5174/',
            'viteServerPublicUrl' => 'https://project.example.test:5173',
        ]);

        self::assertSame(['Development', 'Testing/Local'], $settings->activeContexts());
        self::assertSame('always', $settings->reloadMode());
        self::assertSame('http://vite:5174', $settings->viteServerInternalUrl());
        self::assertSame('https://project.example.test:5173', $settings->viteServerPublicUrl());
    }

    #[Test]
    public function fallsBackToTaggedModeOnUnknownValue(): void
    {
        $settings = $this->settingsWith(['reloadMode' => 'sometimes']);

        self::assertSame('tagged', $settings->reloadMode());
    }

    #[Test]
    public function returnsDefaultsWhenConfigurationThrows(): void
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(new RuntimeException('missing'));
        $settings = new ExtensionSettings($extensionConfiguration);

        self::assertSame(['Development'], $settings->activeContexts());
        self::assertSame('tagged', $settings->reloadMode());
        self::assertSame('http://localhost:5173', $settings->viteServerInternalUrl());
        self::assertSame('', $settings->viteServerPublicUrl());
    }

    private function settingsWith(array $configuration): ExtensionSettings
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('content_live_reload')->willReturn($configuration);

        return new ExtensionSettings($extensionConfiguration);
    }
}
