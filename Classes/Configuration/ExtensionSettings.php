<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Configuration;

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
        return $this->contextAllowedFor((string)Environment::getContext());
    }

    /**
     * Matches whole context segments: "Production/Staging" allows itself and
     * deeper subcontexts, never its parent. A bare "Production" entry is
     * ignored — production-like environments must name their exact subcontext.
     */
    public function contextAllowedFor(string $context): bool
    {
        foreach ($this->activeContexts() as $allowed) {
            if ($allowed === 'Production') {
                continue;
            }
            if ($context === $allowed || str_starts_with($context, $allowed . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * A missing setting defaults to Development; a present but empty
     * value means "disabled everywhere".
     *
     * @return array<string>
     */
    public function activeContexts(): array
    {
        $raw = $this->configuration()['activeContexts'] ?? null;
        if (!is_string($raw)) {
            $raw = 'Development';
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function developmentContext(): bool
    {
        return $this->developmentContextFor((string)Environment::getContext());
    }

    public function developmentContextFor(string $context): bool
    {
        return $context === 'Development' || str_starts_with($context, 'Development/');
    }

    public function pollInterval(): int
    {
        return max(1000, $this->integerValue('pollInterval', 3000));
    }

    public function retention(): int
    {
        return max(60, $this->integerValue('retention', 300));
    }

    public function reloadMode(): string
    {
        return match ($this->stringValue('reloadMode', 'tagged')) {
            'always' => 'always',
            default => 'tagged',
        };
    }

    public function fileReloadEnabled(): bool
    {
        $value = $this->configuration()['fileReload'] ?? null;

        return $value === null || (bool)$value;
    }

    public function fileCaptureActive(): bool
    {
        return $this->contextAllowed() && $this->developmentContext() && $this->fileReloadEnabled();
    }

    public function viteServerInternalUrl(): string
    {
        return rtrim($this->stringValue('viteServerInternalUrl', 'http://localhost:5173'), '/');
    }

    public function viteServerPublicUrl(): string
    {
        return rtrim($this->stringValue('viteServerPublicUrl', ''), '/');
    }

    public function viteSharedSecret(): string
    {
        return $this->stringValue('viteSharedSecret', '');
    }

    private function stringValue(string $key, string $default): string
    {
        $value = $this->configuration()[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private function integerValue(string $key, int $default): int
    {
        $value = $this->configuration()[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit(trim($value))) {
            return (int)trim($value);
        }

        return $default;
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
            $configuration = $this->extensionConfiguration->get('live_reload');
        } catch (Throwable) {
            $configuration = [];
        }

        return $this->configuration = is_array($configuration) ? $configuration : [];
    }
}
