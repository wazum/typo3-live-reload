<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Broadcast;

/**
 * One normalization for every broadcast path, mirroring the Vite
 * plugin's sanitizeTags: what the endpoint would drop on arrival
 * never enters the database log or the HTTP broadcast.
 */
final readonly class TagNormalizer
{
    public const MAXIMUM_TAG_LENGTH = 500;

    public const MAXIMUM_TAGS = 1000;

    /**
     * @param array<string> $tags
     *
     * @return array<string>
     */
    public function normalize(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            if (!mb_check_encoding($tag, 'UTF-8')) {
                continue;
            }
            $tag = trim((string)preg_replace('/[\x00-\x1f\x7f]/', '', $tag));
            if ($tag === '' || strlen($tag) > self::MAXIMUM_TAG_LENGTH) {
                continue;
            }
            $normalized[$tag] = true;
            if (count($normalized) === self::MAXIMUM_TAGS) {
                break;
            }
        }

        return array_keys($normalized);
    }
}
