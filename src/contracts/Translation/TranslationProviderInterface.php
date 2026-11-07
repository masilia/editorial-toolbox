<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Translation;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;

/**
 * A source of machine translations. The bundle ships an adapter for Ibexa's automated translation package (used when it is
 * installed and has a configured provider); implement this interface to plug in anything else.
 */
interface TranslationProviderInterface
{
    /**
     * The providers able to translate from one language to the other (empty when none is available).
     *
     * @return TranslationProvider[]
     */
    public function providers(string $fromLanguage, string $toLanguage): array;

    /**
     * Translated values of the translatable fields, indexed by field identifier. A field missing from the result keeps the
     * value of the source language.
     *
     * @return array<string, \Ibexa\Contracts\Core\FieldType\Value>
     */
    public function translateFields(Content $source, string $fromLanguage, string $toLanguage, string $providerId): array;
}
