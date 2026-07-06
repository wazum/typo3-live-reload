<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Runtime;

interface ResponseDetacher
{
    public function detach(): bool;
}
