<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\LiveReload\Runtime\ResponseDetacher;
use Wazum\LiveReload\Runtime\SapiResponseDetacher;

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
