<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Fluid\Fixtures;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class RecordableViewHelper extends AbstractViewHelper
{
    public function render(): string
    {
        return 'recordable';
    }
}
