<?php

declare(strict_types=1);

namespace Wazum\E2eFixture\Components;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3Fluid\Fluid\Core\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

final class ComponentCollection extends AbstractComponentCollection
{
    private ?TemplatePaths $templatePaths = null;

    public function getTemplatePaths(): TemplatePaths
    {
        if ($this->templatePaths === null) {
            $this->templatePaths = new TemplatePaths();
            $this->templatePaths->setTemplateRootPaths([
                ExtensionManagementUtility::extPath('e2e_fixture', 'Resources/Private/Components'),
            ]);
            $this->templatePaths->setFormat('html');
        }

        return $this->templatePaths;
    }
}
