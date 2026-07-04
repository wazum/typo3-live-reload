<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\AdminPanel;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\AbstractModule;
use TYPO3\CMS\Adminpanel\ModuleApi\PageSettingsProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\RequestEnricherInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ResourceProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ShortInfoProviderInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final class ContentLiveReloadModule extends AbstractModule implements RequestEnricherInterface, PageSettingsProviderInterface, ShortInfoProviderInterface, ResourceProviderInterface
{
    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
    ) {
    }

    public function getIdentifier(): string
    {
        return 'content_live_reload';
    }

    public function getLabel(): string
    {
        return 'Content Live Reload';
    }

    public function getIconIdentifier(): string
    {
        return 'actions-refresh';
    }

    public function getShortInfo(): string
    {
        $mode = $this->configurationService->getConfigurationOption('content_live_reload', 'mode');

        return (in_array($mode, ['tagged', 'always', 'paused'], true) ? $mode : 'tagged') . ' · waiting for the dev server';
    }

    /**
     * @return array<string>
     */
    public function getJavaScriptFiles(): array
    {
        $script = 'EXT:content_live_reload/Resources/Public/JavaScript/admin-panel-broadcasts.js';
        $absolutePath = GeneralUtility::getFileAbsFileName($script);
        $modificationTime = is_file($absolutePath) ? (string)filemtime($absolutePath) : '';

        return [$script . ($modificationTime !== '' ? '?' . $modificationTime : '')];
    }

    /**
     * @return array<string>
     */
    public function getCssFiles(): array
    {
        return [];
    }

    public function enrich(ServerRequestInterface $request): ServerRequestInterface
    {
        $mode = $this->configurationService->getConfigurationOption('content_live_reload', 'mode');

        return match ($mode) {
            'tagged', 'always', 'paused' => $request->withAttribute('content_live_reload.mode', $mode),
            default => $request,
        };
    }

    public function getPageSettings(): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:content_live_reload/Resources/Private/Templates'],
        ));
        $view->assign('mode', $this->configurationService->getConfigurationOption('content_live_reload', 'mode'));

        return $view->render('AdminPanel/Settings');
    }
}
