<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Broadcast;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Wazum\LiveReload\Configuration\ExtensionSettings;

final class DatabaseBroadcastLog implements BroadcastLogInterface
{
    private const TABLE = 'tx_livereload_broadcast';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionSettings $settings,
    ) {
    }

    public function append(array $tags): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->insert(self::TABLE, [
            'tags' => json_encode(array_values($tags), JSON_THROW_ON_ERROR),
            'crdate' => time(),
        ]);
        $this->prune();
    }

    public function since(int $sequence): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('uid', 'tags')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->gt(
                'uid',
                $queryBuilder->createNamedParameter($sequence, Connection::PARAM_INT),
            ))
            ->orderBy('uid')
            ->setMaxResults(self::MAXIMUM_BATCH_SIZE)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            fn (array $row): array => [
                'sequence' => (int)$row['uid'],
                'tags' => $this->decodeTags((string)$row['tags']),
            ],
            $rows,
        );
    }

    public function latestSequence(): int
    {
        return $this->boundarySequence('MAX');
    }

    public function oldestSequence(): int
    {
        return $this->boundarySequence('MIN');
    }

    private function prune(): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->delete(self::TABLE)
            ->where($queryBuilder->expr()->lt(
                'crdate',
                $queryBuilder->createNamedParameter(time() - $this->settings->retention(), Connection::PARAM_INT),
            ))
            ->executeStatement();
    }

    /**
     * @return array<string>
     */
    private function decodeTags(string $encodedTags): array
    {
        $decoded = json_decode($encodedTags, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, is_string(...)));
    }

    private function boundarySequence(string $aggregate): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return (int)$queryBuilder
            ->addSelectLiteral($aggregate . '(uid)')
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchOne();
    }
}
