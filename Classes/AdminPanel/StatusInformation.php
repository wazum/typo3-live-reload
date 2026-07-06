<?php

declare(strict_types=1);

namespace Wazum\LiveReload\AdminPanel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\AbstractSubModule;
use TYPO3\CMS\Adminpanel\ModuleApi\DataProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ModuleData;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Middleware\PollEndpointMiddleware;
use Wazum\LiveReload\Resolver\DevServerUrlResolver;

final class StatusInformation extends AbstractSubModule implements DataProviderInterface
{
    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly DevServerUrlResolver $devServerUrlResolver,
        private readonly Features $features,
        private readonly ViewFactoryInterface $viewFactory,
    ) {
    }

    public function getIdentifier(): string
    {
        return 'live_reload_status';
    }

    public function getLabel(): string
    {
        return 'Status';
    }

    public function getDataToStore(ServerRequestInterface $request, ?ResponseInterface $response = null): ModuleData
    {
        $resolution = $this->devServerUrlResolver->explain($request);
        $modeOverride = $request->getAttribute('live_reload.mode');

        return new ModuleData([
            'contextAllowed' => $this->settings->contextAllowed(),
            'context' => (string)Environment::getContext(),
            'activeContexts' => implode(', ', $this->settings->activeContexts()),
            'mode' => is_string($modeOverride) ? $modeOverride : $this->settings->reloadMode(),
            'modeOverridden' => is_string($modeOverride),
            'transport' => $this->settings->developmentContext() ? 'vite' : 'poll',
            'pollEndpoint' => PollEndpointMiddleware::PATH,
            'pollInterval' => $this->settings->pollInterval(),
            'resolvedUrl' => $resolution['url'],
            'resolutionSource' => $resolution['source'],
            'internalUrl' => $this->settings->viteServerInternalUrl(),
            'autoTagging' => $this->features->isFeatureEnabled('frontend.cache.autoTagging'),
        ]);
    }

    public function getContent(ModuleData $data): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:live_reload/Resources/Private/Templates'],
        ));
        $view->assignMultiple($data->getArrayCopy());

        return $view->render('AdminPanel/Status');
    }
}
