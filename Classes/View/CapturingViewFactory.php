<?php

declare(strict_types=1);

namespace Wazum\LiveReload\View;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ReflectionProperty;
use Throwable;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext as FluidRenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use Wazum\LiveReload\Collector\RenderedFileCollector;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Fluid\CapturingTemplatePaths;
use Wazum\LiveReload\Fluid\CapturingViewHelperResolver;

final class CapturingViewFactory implements ViewFactoryInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ViewFactoryInterface $inner,
        private readonly RenderedFileCollector $collector,
        private readonly ExtensionSettings $settings,
    ) {
    }

    public function create(ViewFactoryData $data): ViewInterface
    {
        $view = $this->inner->create($data);

        if (!$this->settings->developmentContext() || !$view instanceof FluidViewAdapter) {
            return $view;
        }

        try {
            $renderingContext = $view->getRenderingContext();
            $renderingContext->setTemplatePaths(
                CapturingTemplatePaths::fromExisting($renderingContext->getTemplatePaths(), $this->collector),
            );
            $viewHelperResolver = $renderingContext->getViewHelperResolver();
            if ($viewHelperResolver instanceof ViewHelperResolver) {
                $renderingContext->setViewHelperResolver(
                    CapturingViewHelperResolver::fromExisting($viewHelperResolver, $this->collector),
                );
            }
            $this->disableCompileCache($renderingContext);
        } catch (Throwable $exception) {
            $this->logger?->warning('Live reload failed to instrument a view for file capture', ['exception' => $exception]);

            return $view;
        }

        return $view;
    }

    /**
     * Compiled templates skip getTemplateSource(), so a warm compile cache
     * would silently bypass the capture; instrumented views render uncached.
     */
    private function disableCompileCache(RenderingContextInterface $renderingContext): void
    {
        $property = new ReflectionProperty(FluidRenderingContext::class, 'cache');
        $property->setValue($renderingContext, null);
    }
}
