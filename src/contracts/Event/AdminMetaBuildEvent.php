<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Event;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the default admin meta is built; listeners may add or change entries.
 */
final class AdminMetaBuildEvent extends Event
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly Content $content,
        private array $meta,
    ) {
    }

    public function getContent(): Content
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }
}
