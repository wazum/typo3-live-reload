<?php

declare(strict_types=1);

namespace Wazum\E2eFixture\Command;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SeedCommand extends Command
{
    private const EDITOR_USERNAME = 'editor';
    private const EDITOR_PASSWORD = 'Editor1234!Reload';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Bootstrap::initializeBackendAuthentication();

        $this->removeDefaultTypoScriptTemplates();
        $this->ensureEditorUser();

        $output->writeln(json_encode(array_merge($this->contentRecords(), [
            'editorUsername' => self::EDITOR_USERNAME,
            'editorPassword' => self::EDITOR_PASSWORD,
        ]), JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    /**
     * @return array{otherPageUid: int, homeContentUid: int, otherContentUid: int, editorHomePageUid: int, editorHomeContentUid: int, editorOtherPageUid: int, editorOtherContentUid: int}
     */
    private function contentRecords(): array
    {
        $existingOtherPageUid = $this->recordUid('pages', ['slug' => '/other']);
        if ($existingOtherPageUid !== null) {
            $editorHomePageUid = (int)$this->recordUid('pages', ['slug' => '/editor-home']);
            $editorOtherPageUid = (int)$this->recordUid('pages', ['slug' => '/editor-other']);

            return [
                'otherPageUid' => $existingOtherPageUid,
                'homeContentUid' => (int)$this->recordUid('tt_content', ['pid' => 1]),
                'otherContentUid' => (int)$this->recordUid('tt_content', ['pid' => $existingOtherPageUid]),
                'editorHomePageUid' => $editorHomePageUid,
                'editorHomeContentUid' => (int)$this->recordUid('tt_content', ['pid' => $editorHomePageUid]),
                'editorOtherPageUid' => $editorOtherPageUid,
                'editorOtherContentUid' => (int)$this->recordUid('tt_content', ['pid' => $editorOtherPageUid]),
            ];
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'pages' => [
                'NEW_page' => ['pid' => 1, 'title' => 'Other', 'slug' => '/other', 'doktype' => 1, 'hidden' => 0],
                'NEW_editor_home' => ['pid' => 1, 'title' => 'Editor home', 'slug' => '/editor-home', 'doktype' => 1, 'hidden' => 0],
                'NEW_editor_other' => ['pid' => 1, 'title' => 'Editor other', 'slug' => '/editor-other', 'doktype' => 1, 'hidden' => 0],
            ],
            'tt_content' => [
                'NEW_content_home' => ['pid' => 1, 'CType' => 'text', 'header' => 'Home content', 'colPos' => 0],
                'NEW_content_other' => ['pid' => 'NEW_page', 'CType' => 'text', 'header' => 'Other content', 'colPos' => 0],
                'NEW_content_editor_home' => ['pid' => 'NEW_editor_home', 'CType' => 'text', 'header' => 'Editor home content', 'colPos' => 0],
                'NEW_content_editor_other' => ['pid' => 'NEW_editor_other', 'CType' => 'text', 'header' => 'Editor other content', 'colPos' => 0],
            ],
        ], []);
        $dataHandler->process_datamap();

        return [
            'otherPageUid' => (int)$dataHandler->substNEWwithIDs['NEW_page'],
            'homeContentUid' => (int)$dataHandler->substNEWwithIDs['NEW_content_home'],
            'otherContentUid' => (int)$dataHandler->substNEWwithIDs['NEW_content_other'],
            'editorHomePageUid' => (int)$dataHandler->substNEWwithIDs['NEW_editor_home'],
            'editorHomeContentUid' => (int)$dataHandler->substNEWwithIDs['NEW_content_editor_home'],
            'editorOtherPageUid' => (int)$dataHandler->substNEWwithIDs['NEW_editor_other'],
            'editorOtherContentUid' => (int)$dataHandler->substNEWwithIDs['NEW_content_editor_other'],
        ];
    }

    private function removeDefaultTypoScriptTemplates(): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_template')
            ->truncate('sys_template');
    }

    private function ensureEditorUser(): void
    {
        if ($this->recordUid('be_users', ['username' => self::EDITOR_USERNAME]) !== null) {
            return;
        }

        $passwordHash = GeneralUtility::makeInstance(PasswordHashFactory::class)
            ->getDefaultHashInstance('BE')
            ->getHashedPassword(self::EDITOR_PASSWORD);
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_users')
            ->insert('be_users', [
                'username' => self::EDITOR_USERNAME,
                'password' => $passwordHash,
                'admin' => 1,
                'tstamp' => time(),
                'crdate' => time(),
            ]);
    }

    /**
     * @param array<string, int|string> $criteria
     */
    private function recordUid(string $table, array $criteria): ?int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $constraints = [];
        foreach ($criteria as $field => $value) {
            $constraints[] = $queryBuilder->expr()->eq(
                $field,
                is_int($value) ? $queryBuilder->createNamedParameter($value, ParameterType::INTEGER) : $queryBuilder->createNamedParameter($value),
            );
        }
        $uid = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(...$constraints)
            ->orderBy('uid', 'ASC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $uid === false ? null : (int)$uid;
    }
}
