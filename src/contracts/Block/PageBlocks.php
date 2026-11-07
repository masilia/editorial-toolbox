<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Block;

final class PageBlocks
{
    /**
     * @param array<int, array{identifier: string, name: string}> $allowedContentTypes
     * @param BlockDescriptor[] $blocks
     */
    public function __construct(
        public readonly array $allowedContentTypes,
        public readonly array $blocks,
    ) {
    }

    /**
     * @return array{allowed_content_types: array<int, array{identifier: string, name: string}>, blocks: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'allowed_content_types' => $this->allowedContentTypes,
            'blocks' => array_map(static fn (BlockDescriptor $block): array => $block->toArray(), $this->blocks),
        ];
    }
}
