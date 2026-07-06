<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Collector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\LiveReload\Collector\BroadcastTagCollector;
use Wazum\LiveReload\Tests\Support\RecordingBroadcaster;
use Wazum\LiveReload\Tests\Support\RecordingDetacher;

final class BroadcastTagCollectorTest extends TestCase
{
    #[Test]
    public function flushBroadcastsDeduplicatedTagsAfterDetaching(): void
    {
        $broadcaster = new RecordingBroadcaster();
        $detacher = new RecordingDetacher();

        $collector = new BroadcastTagCollector($broadcaster, $detacher);
        $collector->add('pageId_42', 'tt_content_5');
        $collector->add('tt_content_5', 'tt_content');
        $collector->flush();

        self::assertSame([['pageId_42', 'tt_content_5', 'tt_content']], $broadcaster->received);
        self::assertSame(1, $detacher->detachCalls);
    }

    #[Test]
    public function flushIsIdempotentAndSkipsEmptyState(): void
    {
        $broadcaster = new RecordingBroadcaster();
        $detacher = new RecordingDetacher();

        $collector = new BroadcastTagCollector($broadcaster, $detacher);
        $collector->flush();
        $collector->add('pageId_1');
        $collector->flush();
        $collector->flush();

        self::assertSame([['pageId_1']], $broadcaster->received);
        self::assertSame(1, $detacher->detachCalls);
    }
}
