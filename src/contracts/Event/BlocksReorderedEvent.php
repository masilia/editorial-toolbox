<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class BlocksReorderedEvent extends Event
{
    /**
     * @param int[] $blockIds
     */
    public function __construct(
        private readonly int $locationId,
        private readonly array $blockIds,
    ) {
    }

    public function getLocationId(): int
    {
        return $this->locationId;
    }

    /**
     * @return int[]
     */
    public function getBlockIds(): array
    {
        return $this->blockIds;
    }
}
