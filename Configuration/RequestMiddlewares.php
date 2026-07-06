<?php

declare(strict_types=1);

use Wazum\LiveReload\Middleware\PollEndpointMiddleware;
use Wazum\LiveReload\Middleware\TagInjectionMiddleware;

return [
    'frontend' => [
        'wazum/live-reload/poll-endpoint' => [
            'target' => PollEndpointMiddleware::class,
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
        'wazum/live-reload/tag-injection' => [
            'target' => TagInjectionMiddleware::class,
            'after' => [
                'typo3/cms-frontend/csp-headers',
                'typo3/cms-frontend/content-length-headers',
            ],
            'before' => [
                'typo3/cms-core/response-propagation',
            ],
        ],
    ],
];
