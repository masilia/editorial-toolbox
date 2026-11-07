<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Block;

/**
 * Operations on the ordered list of block contents attached to a page (a RelationList field).
 */
interface PageBlocksServiceInterface
{
    public function load(int $locationId): PageBlocks;

    /**
     * Attaches an existing block content to the page; $position null appends.
     */
    public function addRelation(int $locationId, int $blockContentId, ?int $position = null): void;

    /**
     * Detaches the block from the page and moves it to the trash.
     */
    public function remove(int $locationId, int $blockContentId): void;

    /**
     * @param int[] $blockIds new order; ids that are not currently attached are ignored
     */
    public function reorder(int $locationId, array $blockIds): void;

    /**
     * Deletes the block's version if (and only if) it is still a draft.
     */
    public function discardBlockDraft(int $blockContentId): void;
}
