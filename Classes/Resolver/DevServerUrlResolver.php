<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Resolver;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Wazum\LiveReload\Configuration\ExtensionSettings;

final class DevServerUrlResolver
{
    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly ?object $viteAssetCollectorService = null,
    ) {
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        return $this->explain($request)['url'];
    }

    /**
     * @return array{url: string|null, source: string}
     */
    public function explain(ServerRequestInterface $request): array
    {
        $configured = $this->settings->viteServerPublicUrl();
        if ($configured !== '') {
            return ['url' => $configured, 'source' => 'setting'];
        }

        if ($this->viteAssetCollectorService === null) {
            return ['url' => null, 'source' => 'vite-asset-collector not installed'];
        }

        try {
            if (!$this->viteAssetCollectorService->useDevServer()) {
                return ['url' => null, 'source' => 'vite-asset-collector dev server disabled'];
            }

            return [
                'url' => rtrim((string)$this->viteAssetCollectorService->determineDevServer($request), '/'),
                'source' => 'vite-asset-collector',
            ];
        } catch (Throwable) {
            return ['url' => null, 'source' => 'vite-asset-collector error'];
        }
    }
}
