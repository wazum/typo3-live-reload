<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Wazum\ContentLiveReload\AdminPanel\BroadcastsInformation;
use Wazum\ContentLiveReload\AdminPanel\CacheTagsInformation;
use Wazum\ContentLiveReload\AdminPanel\ContentLiveReloadModule;
use Wazum\ContentLiveReload\AdminPanel\StatusInformation;
use Wazum\ContentLiveReload\Hook\ClearCachePostProcHook;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['content_live_reload']
    = ClearCachePostProcHook::class . '->postProcessClearCache';

if (ExtensionManagementUtility::isLoaded('adminpanel')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['content_live_reload'] = [
        'module' => ContentLiveReloadModule::class,
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
