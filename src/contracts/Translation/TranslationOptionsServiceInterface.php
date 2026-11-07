<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Translation;

interface TranslationOptionsServiceInterface
{
    public function options(int $contentId, string $targetLanguage): TranslationOptions;
}
