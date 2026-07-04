<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Tests\Support;

use Wazum\ContentLiveReload\Runtime\ResponseDetacher;

final class RecordingDetacher implements ResponseDetacher
{
    public int $detachCalls = 0;

    public function detach(): bool
    {
        ++$this->detachCalls;

        return true;
    }
}
