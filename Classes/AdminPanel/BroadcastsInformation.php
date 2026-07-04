<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\AdminPanel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\AbstractSubModule;
use TYPO3\CMS\Adminpanel\ModuleApi\DataProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ModuleData;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final class BroadcastsInformation extends AbstractSubModule implements DataProviderInterface
{
    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
    ) {
    }

    public function getIdentifier(): string
    {
        return 'content_live_reload_broadcasts';
    }

    public function getLabel(): string
    {
        return 'Broadcasts';
    }

    public function getDataToStore(ServerRequestInterface $request, ?ResponseInterface $response = null): ModuleData
    {
        return new ModuleData([]);
    }

    public function getContent(ModuleData $data): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:content_live_reload/Resources/Private/Templates'],
        ));

        return $view->render('AdminPanel/Broadcasts');
    }
}
