<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Block;

final class BlockDescriptor
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $contentTypeIdentifier,
        public readonly string $contentTypeName,
        public readonly bool $enabled,
    ) {
    }

    /**
     * @return array{id: int, name: string, content_type: string, content_type_name: string, enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'content_type' => $this->contentTypeIdentifier,
            'content_type_name' => $this->contentTypeName,
            'enabled' => $this->enabled,
        ];
    }
}
