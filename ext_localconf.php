<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Wazum\LiveReload\AdminPanel\BroadcastsInformation;
use Wazum\LiveReload\AdminPanel\CacheTagsInformation;
use Wazum\LiveReload\AdminPanel\LiveReloadModule;
use Wazum\LiveReload\AdminPanel\StatusInformation;
use Wazum\LiveReload\Hook\ClearCachePostProcHook;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['live_reload']
    = ClearCachePostProcHook::class . '->postProcessClearCache';

if (ExtensionManagementUtility::isLoaded('adminpanel')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['live_reload'] = [
        'module' => LiveReloadModule::class,
        'after' => ['info'],
        'submodules' => [
            'status' => [
                'module' => StatusInformation::class,
            ],
            'cachetags' => [
                'module' => CacheTagsInformation::class,
            ],
            'broadcasts' => [
                'module' => BroadcastsInformation::class,
            ],
        ],
    ];
}
