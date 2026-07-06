<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Support;

use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;

trait SwitchesApplicationContext
{
    private string $originalApplicationContext = '';

    private function switchApplicationContext(string $context): void
    {
        if ($this->originalApplicationContext === '') {
            $this->originalApplicationContext = (string)Environment::getContext();
        }

        Environment::initialize(
            new ApplicationContext($context),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
    }

    private function restoreApplicationContext(): void
    {
        if ($this->originalApplicationContext === '') {
            return;
        }

        $this->switchApplicationContext($this->originalApplicationContext);
        $this->originalApplicationContext = '';
    }
}
