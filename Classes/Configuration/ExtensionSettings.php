<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Configuration;

use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;

final class ExtensionSettings
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $configuration = null;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function contextAllowed(): bool
    {
        $context = (string)Environment::getContext();
        foreach ($this->activeContexts() as $allowed) {
            if (str_starts_with($context, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    public function activeContexts(): array
    {
        $raw = $this->stringValue('activeContexts', 'Development');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function reloadMode(): string
    {
        return match ($this->stringValue('reloadMode', 'tagged')) {
            'always' => 'always',
            default => 'tagged',
        };
    }

    public function viteServerInternalUrl(): string
    {
        return rtrim($this->stringValue('viteServerInternalUrl', 'http://localhost:5173'), '/');
    }

    public function viteServerPublicUrl(): string
    {
        return rtrim($this->stringValue('viteServerPublicUrl', ''), '/');
    }

    private function stringValue(string $key, string $default): string
    {
        $value = $this->configuration()[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }

        try {
            $configuration = $this->extensionConfiguration->get('content_live_reload');
        } catch (Throwable) {
            $configuration = [];
        }

        return $this->configuration = is_array($configuration) ? $configuration : [];
    }
}
