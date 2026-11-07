<?php

declare(strict_types=1);

namespace Masilia\EditorialToolbox\Translation;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProviderInterface;

/**
 * Used when no translation provider is installed or configured: the editor is only offered a blank or a copied translation.
 */
class NullTranslationProvider implements TranslationProviderInterface
{
    public function providers(string $fromLanguage, string $toLanguage): array
    {
        return [];
    }

    public function translateFields(Content $source, string $fromLanguage, string $toLanguage, string $providerId): array
    {
        throw new \LogicException('No translation provider is available.');
    }
}
