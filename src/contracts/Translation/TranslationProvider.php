<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Translation;

final class TranslationProvider
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }

    /**
     * @return array{id: string, name: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
