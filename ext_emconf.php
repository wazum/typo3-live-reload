<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Content Live Reload',
    'description' => 'Live-reloads the open frontend tabs affected by a content change in the backend, using TYPO3 cache tags to reload only the tabs that show the changed record.',
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
