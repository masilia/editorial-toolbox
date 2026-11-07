<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Translation;

/**
 * What can be done about the missing translation of a content: the languages it already has (to copy from) and the
 * providers able to translate into the missing one.
 */
final class TranslationOptions
{
    /**
     * @param array{code: string, name: string}                   $target
     * @param array<int, array{code: string, name: string}>       $availableLanguages
     * @param TranslationProvider[]                               $providers
     */
    public function __construct(
        public readonly array $target,
        public readonly array $availableLanguages,
        public readonly string $defaultSource,
        public readonly array $providers,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'available_languages' => $this->availableLanguages,
            'default_source' => $this->defaultSource,
            'providers' => array_map(static fn (TranslationProvider $p): array => $p->toArray(), $this->providers),
        ];
    }
}
