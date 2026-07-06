<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Event;

final class ModifyBroadcastTagsEvent
{
    /**
     * @var array<string>
     */
    private array $tags;

    /**
     * @param array<string> $tags
     */
    public function __construct(
        private readonly string $table,
        private readonly int $uid,
        private readonly int $uidPage,
        array $tags,
    ) {
        $this->tags = array_values($tags);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getUidPage(): int
    {
        return $this->uidPage;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function addTags(string ...$tags): void
    {
        foreach ($tags as $tag) {
            if (!in_array($tag, $this->tags, true)) {
                $this->tags[] = $tag;
            }
        }
    }
}
