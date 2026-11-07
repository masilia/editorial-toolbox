<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class BlockAddedEvent extends Event
{
    public function __construct(
        private readonly int $locationId,
        private readonly int $blockContentId,
    ) {
    }

    public function getLocationId(): int
    {
        return $this->locationId;
    }

    public function getBlockContentId(): int
    {
        return $this->blockContentId;
    }
}
