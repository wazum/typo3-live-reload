<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Broadcaster;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\LiveReload\Broadcaster\BroadcastLogWriter;
use Wazum\LiveReload\Tests\Support\InMemoryBroadcastLog;

final class BroadcastLogWriterTest extends TestCase
{
    #[Test]
    public function appendsBroadcastTagsToTheLog(): void
    {
        $log = new InMemoryBroadcastLog();
        $writer = new BroadcastLogWriter($log);

        $writer->broadcast('pageId_42', 'tt_content_5');

        self::assertSame([['sequence' => 1, 'tags' => ['pageId_42', 'tt_content_5']]], $log->entries);
    }

    #[Test]
    public function skipsEmptyBroadcasts(): void
    {
        $log = new InMemoryBroadcastLog();
        $writer = new BroadcastLogWriter($log);

        $writer->broadcast();

        self::assertSame([], $log->entries);
    }
}
