<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Tests\Unit\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\ContentLiveReload\Runtime\ResponseDetacher;
use Wazum\ContentLiveReload\Runtime\SapiResponseDetacher;

final class SapiResponseDetacherTest extends TestCase
{
    #[Test]
    public function returnsFalseUnderCliWithoutThrowing(): void
    {
        $detacher = new SapiResponseDetacher();

        self::assertInstanceOf(ResponseDetacher::class, $detacher);
        self::assertFalse($detacher->detach());
    }
}
