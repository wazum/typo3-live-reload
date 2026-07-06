<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Fluid\Fixtures;

use TYPO3Fluid\Fluid\Core\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

final class TestComponentCollection extends AbstractComponentCollection
{
    private ?TemplatePaths $templatePaths = null;

    public function getTemplatePaths(): TemplatePaths
    {
        if ($this->templatePaths === null) {
            $this->templatePaths = new TemplatePaths();
            $this->templatePaths->setTemplateRootPaths([__DIR__ . '/Components']);
            $this->templatePaths->setFormat('html');
        }

        return $this->templatePaths;
    }
}
