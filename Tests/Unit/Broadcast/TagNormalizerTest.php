<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Unit\Broadcast;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\LiveReload\Broadcast\TagNormalizer;

final class TagNormalizerTest extends TestCase
{
    private TagNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new TagNormalizer();
    }

    #[Test]
    public function keepsRegularTagsInOrder(): void
    {
        self::assertSame(
            ['tt_content_5', 'pageId_42'],
            $this->normalizer->normalize(['tt_content_5', 'pageId_42']),
        );
    }

    #[Test]
    public function dropsEmptyAndWhitespaceOnlyTags(): void
    {
        self::assertSame(
            ['pageId_42'],
            $this->normalizer->normalize(['', '   ', 'pageId_42']),
        );
    }

    #[Test]
    public function stripsControlCharactersAndTrims(): void
    {
        self::assertSame(
            ['pageId_42'],
            $this->normalizer->normalize([" page\x00Id_4\x1f2 \x7f"]),
        );
    }

    #[Test]
    public function dropsTagsThatAreNotValidUtf8(): void
    {
        self::assertSame(
            ['pageId_42'],
            $this->normalizer->normalize(["\xB1\x31", 'pageId_42']),
        );
    }

    #[Test]
    public function dropsTagsLongerThanTheMaximumLength(): void
    {
        $oversized = str_repeat('a', 501);
        $maximal = str_repeat('b', 500);

        self::assertSame(
            [$maximal],
            $this->normalizer->normalize([$oversized, $maximal]),
        );
    }

    #[Test]
    public function deduplicatesTags(): void
    {
        self::assertSame(
            ['pageId_42', 'tt_content_5'],
            $this->normalizer->normalize(['pageId_42', 'tt_content_5', 'pageId_42']),
        );
    }

    #[Test]
    public function capsTheNumberOfTags(): void
    {
        $tags = array_map(static fn (int $index): string => 'tag_' . $index, range(1, 1001));

        $normalized = $this->normalizer->normalize($tags);

        self::assertCount(1000, $normalized);
        self::assertSame('tag_1', $normalized[0]);
        self::assertSame('tag_1000', $normalized[999]);
    }
}
