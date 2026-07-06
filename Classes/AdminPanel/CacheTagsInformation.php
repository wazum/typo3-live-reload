<?php

declare(strict_types=1);

namespace Wazum\LiveReload\AdminPanel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\AbstractSubModule;
use TYPO3\CMS\Adminpanel\ModuleApi\DataProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ModuleData;
use TYPO3\CMS\Core\Cache\CacheDataCollectorInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final class CacheTagsInformation extends AbstractSubModule implements DataProviderInterface
{
    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
    ) {
    }

    public function getIdentifier(): string
    {
        return 'live_reload_cachetags';
    }

    public function getLabel(): string
    {
        return 'Cache tags';
    }

    public function getDataToStore(ServerRequestInterface $request, ?ResponseInterface $response = null): ModuleData
    {
        $tags = [];
        $collector = $request->getAttribute('frontend.cache.collector');
        if ($collector instanceof CacheDataCollectorInterface) {
            foreach ($collector->getCacheTags() as $cacheTag) {
                $tags[] = $cacheTag->name;
            }
        }
        sort($tags);

        return new ModuleData([
            'tags' => $tags,
            'count' => count($tags),
            'collectorAvailable' => $collector instanceof CacheDataCollectorInterface,
        ]);
    }

    public function getContent(ModuleData $data): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:live_reload/Resources/Private/Templates'],
        ));
        $view->assignMultiple($data->getArrayCopy());

        return $view->render('AdminPanel/CacheTags');
    }
}
