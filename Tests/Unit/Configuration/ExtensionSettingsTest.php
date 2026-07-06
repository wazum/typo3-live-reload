<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Wazum\LiveReload\Configuration\ExtensionSettings;

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
    public function fileReloadIsEnabledByDefaultAndCanBeDisabled(): void
    {
        self::assertTrue($this->settingsWith([])->fileReloadEnabled());
        self::assertTrue($this->settingsWith(['fileReload' => '1'])->fileReloadEnabled());
        self::assertFalse($this->settingsWith(['fileReload' => '0'])->fileReloadEnabled());
        self::assertFalse($this->settingsWith(['fileReload' => false])->fileReloadEnabled());
    }

    #[Test]
    public function allowsExactContextAndSubcontexts(): void
    {
        $settings = $this->settingsWith(['activeContexts' => 'Development']);

        self::assertTrue($settings->contextAllowedFor('Development'));
        self::assertTrue($settings->contextAllowedFor('Development/Docker'));
        self::assertFalse($settings->contextAllowedFor('Development2'));
        self::assertFalse($settings->contextAllowedFor('Testing'));
    }

    #[Test]
    public function allowsProductionSubcontextWithoutItsParent(): void
    {
        $settings = $this->settingsWith(['activeContexts' => 'Production/Staging']);

        self::assertTrue($settings->contextAllowedFor('Production/Staging'));
        self::assertTrue($settings->contextAllowedFor('Production/Staging/Cluster1'));
        self::assertFalse($settings->contextAllowedFor('Production'));
    }

    #[Test]
    public function ignoresABareProductionEntry(): void
    {
        $settings = $this->settingsWith(['activeContexts' => 'Production']);

        self::assertFalse($settings->contextAllowedFor('Production'));
        self::assertFalse($settings->contextAllowedFor('Production/Staging'));
    }

    #[Test]
    public function recognizesDevelopmentContextAndItsSubcontexts(): void
    {
        $settings = $this->settingsWith([]);

        self::assertTrue($settings->developmentContextFor('Development'));
        self::assertTrue($settings->developmentContextFor('Development/Docker'));
        self::assertFalse($settings->developmentContextFor('Development2'));
        self::assertFalse($settings->developmentContextFor('Testing'));
        self::assertFalse($settings->developmentContextFor('Production/Staging'));
    }

    #[Test]
    public function returnsDefaultPollIntervalAndRetention(): void
    {
        $settings = $this->settingsWith([]);

        self::assertSame(3000, $settings->pollInterval());
        self::assertSame(300, $settings->retention());
    }

    #[Test]
    public function parsesConfiguredPollIntervalAndRetention(): void
    {
        $settings = $this->settingsWith(['pollInterval' => '5000', 'retention' => '600']);

        self::assertSame(5000, $settings->pollInterval());
        self::assertSame(600, $settings->retention());
    }

    #[Test]
    public function enforcesMinimumPollIntervalAndRetention(): void
    {
        $settings = $this->settingsWith(['pollInterval' => '100', 'retention' => '5']);

        self::assertSame(1000, $settings->pollInterval());
        self::assertSame(60, $settings->retention());
    }

    #[Test]
    public function fallsBackToDefaultsOnNonNumericPollIntervalAndRetention(): void
    {
        $settings = $this->settingsWith(['pollInterval' => 'fast', 'retention' => '-10']);

        self::assertSame(3000, $settings->pollInterval());
        self::assertSame(300, $settings->retention());
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
        $extensionConfiguration->method('get')->with('live_reload')->willReturn($configuration);

        return new ExtensionSettings($extensionConfiguration);
    }
}
