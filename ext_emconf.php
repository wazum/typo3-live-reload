<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Live Reload',
    'description' => 'Live-reloads the open frontend tabs affected by a content or source-file change, using TYPO3 cache tags and rendered-file tags to reload only the affected tabs.',
    'category' => 'misc',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'version' => '2.2.0',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-14.99.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
];
