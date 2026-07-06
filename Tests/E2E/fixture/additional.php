<?php

$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverOptions'][PDO::ATTR_TIMEOUT] = 30;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = true;
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload'] = [
    'activeContexts' => 'Development,Production/Staging',
    'reloadMode' => 'tagged',
    'viteServerInternalUrl' => 'http://127.0.0.1:5273',
    'viteServerPublicUrl' => '',
];
