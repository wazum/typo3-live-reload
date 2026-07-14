<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Hook;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\LiveReload\Broadcast\TagNormalizer;
use Wazum\LiveReload\Collector\BroadcastTagCollector;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Event\ModifyBroadcastTagsEvent;

final class ClearCachePostProcHook implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly BroadcastTagCollector $collector,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TagNormalizer $tagNormalizer = new TagNormalizer(),
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function postProcessClearCache(array $parameters, DataHandler $dataHandler): void
    {
        if (!$this->settings->contextAllowed()) {
            return;
        }

        try {
            $tags = $this->normalizeTags((array)($parameters['tags'] ?? []));
            if ($tags === []) {
                return;
            }

            /** @var ModifyBroadcastTagsEvent $event */
            $event = $this->eventDispatcher->dispatch(new ModifyBroadcastTagsEvent(
                (string)($parameters['table'] ?? ''),
                (int)($parameters['uid'] ?? 0),
                (int)($parameters['uid_page'] ?? 0),
                $tags,
            ));

            $this->collector->add(...$this->tagNormalizer->normalize($event->getTags()));
        } catch (Throwable $exception) {
            $this->logger?->warning('Collecting broadcast tags failed', ['exception' => $exception]);
        }
    }

    /**
     * DataHandler passes a tag => true map on record saves,
     * but a plain list of tags from clear_cacheCmd().
     *
     * @param array<int|string, mixed> $rawTags
     *
     * @return array<string>
     */
    private function normalizeTags(array $rawTags): array
    {
        if (array_is_list($rawTags)) {
            return array_values(array_filter(
                $rawTags,
                static fn (mixed $tag): bool => is_string($tag) && $tag !== '',
            ));
        }

        return array_filter(array_keys(array_filter($rawTags)), is_string(...));
    }
}
