<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Content Live Reload',
    'description' => 'Live-reloads open browser tabs when backend record changes invalidate the content they display, driven by TYPO3 cache tags and the Vite dev server.',
    'category' => 'misc',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-14.99.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
];
