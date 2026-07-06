<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceKeyword;
use TYPO3\CMS\Core\Type\Map;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\LiveReload\Configuration\ExtensionSettings;

$settings = new ExtensionSettings(GeneralUtility::makeInstance(ExtensionConfiguration::class));
if (!$settings->contextAllowed()) {
    return Map::fromEntries();
}

return Map::fromEntries([
    Scope::frontend(),
    new MutationCollection(
        new Mutation(MutationMode::Extend, Directive::ScriptSrc, SourceKeyword::nonceProxy),
        new Mutation(MutationMode::Extend, Directive::ScriptSrcElem, SourceKeyword::nonceProxy),
    ),
]);
