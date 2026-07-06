<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Fluid;

use TYPO3Fluid\Fluid\Core\Component\ComponentDefinition;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinitionProviderInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentListProviderInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentRenderer;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolverDelegateInterface;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use Wazum\LiveReload\Collector\RenderedFileCollector;

/**
 * Component collections build their own TemplatePaths outside the decorated
 * view factory, so component templates would escape the file capture. This
 * wrapper hands the component renderer capturing paths instead.
 */
final class CapturingComponentCollection implements ViewHelperResolverDelegateInterface, ComponentDefinitionProviderInterface, ComponentTemplateResolverInterface, ComponentListProviderInterface
{
    private ?CapturingTemplatePaths $templatePaths = null;

    public function __construct(
        private readonly ViewHelperResolverDelegateInterface&ComponentDefinitionProviderInterface&ComponentTemplateResolverInterface $inner,
        private readonly RenderedFileCollector $collector,
    ) {
    }

    public function getTemplatePaths(): TemplatePaths
    {
        return $this->templatePaths ??= CapturingTemplatePaths::fromExisting($this->inner->getTemplatePaths(), $this->collector);
    }

    public function getComponentRenderer(): ComponentRendererInterface
    {
        return new ComponentRenderer($this);
    }

    public function getComponentDefinition(string $viewHelperName): ComponentDefinition
    {
        return $this->inner->getComponentDefinition($viewHelperName);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalVariables(string $viewHelperName): array
    {
        return $this->inner->getAdditionalVariables($viewHelperName);
    }

    public function resolveTemplateName(string $viewHelperName): string
    {
        return $this->inner->resolveTemplateName($viewHelperName);
    }

    public function resolveViewHelperClassName(string $name): string
    {
        return $this->inner->resolveViewHelperClassName($name);
    }

    public function getNamespace(): string
    {
        return $this->inner->getNamespace();
    }

    /**
     * @return string[]
     */
    public function getAvailableComponents(): array
    {
        return $this->inner instanceof ComponentListProviderInterface ? $this->inner->getAvailableComponents() : [];
    }
}
