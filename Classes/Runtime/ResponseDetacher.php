<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Runtime;

interface ResponseDetacher
{
    public function detach(): bool;
}
