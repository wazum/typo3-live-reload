<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Broadcast;

interface BroadcastLogInterface
{
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
