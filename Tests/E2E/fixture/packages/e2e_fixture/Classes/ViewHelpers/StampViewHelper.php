<?php

declare(strict_types=1);

namespace Wazum\E2eFixture\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class StampViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function render(): string
    {
        return '<div data-fixture="stamp">stamp</div>';
    }
}
