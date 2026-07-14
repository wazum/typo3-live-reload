<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Broadcast;

interface BroadcastLogInterface
{
    /**
     * since() returns at most this many entries, and the poll endpoint
     * answers "stale" for any larger gap — one constant, so the two
     * limits can never drift apart.
     */
    public const MAXIMUM_BATCH_SIZE = 100;

    /**
     * @param array<string> $tags
     */
    public function append(array $tags): void;

    /**
     * @return array<int, array{sequence: int, tags: array<string>}>
     */
    public function since(int $sequence): array;

    public function latestSequence(): int;

    public function oldestSequence(): int;
}
