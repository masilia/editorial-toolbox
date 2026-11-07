<?php

declare(strict_types=1);

namespace Masilia\EditorialToolbox\Block;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\TrashService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Masilia\Contracts\EditorialToolbox\Block\BlockDescriptor;
use Masilia\Contracts\EditorialToolbox\Block\PageBlocks;
use Masilia\Contracts\EditorialToolbox\Block\PageBlocksServiceInterface;
use Masilia\Contracts\EditorialToolbox\Event\BlockAddedEvent;
use Masilia\Contracts\EditorialToolbox\Event\BlockRemovedEvent;
use Masilia\Contracts\EditorialToolbox\Event\BlocksReorderedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PageBlocksService implements PageBlocksServiceInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly LocationService $locationService,
        private readonly TrashService $trashService,
        private readonly ConfigResolverInterface $configResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(int $locationId): PageBlocks
    {
        $languages = $this->configResolver->getParameter('languages');
        $location = $this->locationService->loadLocation($locationId);
        $content = $this->contentService->loadContent($location->contentId, $languages);

        $fieldDefinition = $content->getContentType()->getFieldDefinition($this->blocksField());
        $allowedIdentifiers = $fieldDefinition?->fieldSettings['selectionContentTypes'] ?? [];

        $allowedContentTypes = [];
        foreach ($allowedIdentifiers as $identifier) {
            try {
                $name = $this->contentTypeService->loadContentTypeByIdentifier($identifier)->getName();
            } catch (\Exception) {
                $name = $identifier;
            }
            $allowedContentTypes[] = ['identifier' => $identifier, 'name' => $name ?? $identifier];
        }

        $blocks = [];
        foreach ($this->currentBlockIds($content) as $blockId) {
            try {
                $block = $this->contentService->loadContent($blockId, $languages);
            } catch (\Exception) {
                continue; // skip invalid relations
            }

            $blocks[] = new BlockDescriptor(
                $block->id,
                (string) $block->getName($languages[0] ?? null),
                $block->getContentType()->identifier,
                (string) $block->getContentType()->getName(),
                !$block->contentInfo->isHidden,
            );
        }

        return new PageBlocks($allowedContentTypes, $blocks);
    }

    public function addRelation(int $locationId, int $blockContentId, ?int $position = null): void
    {
        $this->updateBlockIds($locationId, static function (array $ids) use ($blockContentId, $position): array {
            if ($position !== null && $position >= 0 && $position <= \count($ids)) {
                array_splice($ids, $position, 0, [$blockContentId]);
            } else {
                $ids[] = $blockContentId;
            }

            return $ids;
        });

        $this->eventDispatcher->dispatch(new BlockAddedEvent($locationId, $blockContentId));
    }

    public function remove(int $locationId, int $blockContentId): void
    {
        $this->updateBlockIds(
            $locationId,
            static fn (array $ids): array => array_values(array_filter($ids, static fn (int $id): bool => $id !== $blockContentId))
        );

        $block = $this->contentService->loadContent($blockContentId);
        $this->trashService->trash($this->locationService->loadLocation($block->contentInfo->mainLocationId));

        $this->eventDispatcher->dispatch(new BlockRemovedEvent($locationId, $blockContentId));
    }

    public function reorder(int $locationId, array $blockIds): void
    {
        $requested = array_map('intval', $blockIds);

        $this->updateBlockIds(
            $locationId,
            static fn (array $current): array => array_values(array_filter(
                $requested,
                static fn (int $id): bool => \in_array($id, $current, true)
            ))
        );

        $this->eventDispatcher->dispatch(new BlocksReorderedEvent($locationId, $requested));
    }

    public function discardBlockDraft(int $blockContentId): void
    {
        $block = $this->contentService->loadContent($blockContentId);
        if ($block->versionInfo->status === VersionInfo::STATUS_DRAFT) {
            $this->contentService->deleteVersion($block->versionInfo);
        }
    }

    /**
     * Creates a draft of the page, rewrites its blocks relation list and publishes it.
     * If anything fails the draft is deleted again so no orphan drafts pile up.
     *
     * @param callable(int[]): int[] $mutator
     */
    private function updateBlockIds(int $locationId, callable $mutator): void
    {
        $location = $this->locationService->loadLocation($locationId);
        $page = $this->contentService->loadContent($location->contentId);

        $draft = $this->contentService->createContentDraft($page->contentInfo);

        try {
            $ids = array_map('intval', $this->currentBlockIds($draft));

            $struct = $this->contentService->newContentUpdateStruct();
            $struct->setField($this->blocksField(), new RelationListValue(array_values($mutator($ids))));

            $this->contentService->updateContent($draft->versionInfo, $struct);
            $this->contentService->publishVersion($draft->versionInfo);
        } catch (\Throwable $e) {
            try {
                $this->contentService->deleteVersion($draft->versionInfo);
            } catch (\Throwable) {
                // keep the original failure
            }

            throw $e;
        }
    }

    /**
     * @return int[]
     */
    private function currentBlockIds(Content $content): array
    {
        $value = $content->getFieldValue($this->blocksField());

        return array_map('intval', $value->destinationContentIds ?? []);
    }

    private function blocksField(): string
    {
        return $this->configResolver->getParameter('blocks_field', 'masilia_editorial_toolbox');
    }
}
