<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Support;

use Wazum\LiveReload\Broadcast\BroadcastLogInterface;

final class InMemoryBroadcastLog implements BroadcastLogInterface
{
    /**
     * @var array<int, array{sequence: int, tags: array<string>}>
     */
    public array $entries = [];

    private int $sequence = 0;

    public function append(array $tags): void
    {
        $this->entries[] = ['sequence' => ++$this->sequence, 'tags' => array_values($tags)];
    }

    public function since(int $sequence): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => $entry['sequence'] > $sequence,
        ));
    }

    public function latestSequence(): int
    {
        return $this->sequence;
    }

    public function oldestSequence(): int
    {
        return $this->entries[0]['sequence'] ?? 0;
    }
}
