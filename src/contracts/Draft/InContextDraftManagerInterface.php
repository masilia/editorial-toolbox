<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Draft;

use Masilia\Contracts\EditorialToolbox\Translation\TranslationRequest;

interface InContextDraftManagerInterface
{
    /**
     * Returns the version number of the current user's draft for this language, creating it when missing.
     *
     * When the content has no translation in $languageCode, $translation says what to create (blank, copied or provider
     * translated); without it a TranslationMissingException is thrown, so that no blank translation is ever created by accident.
     *
     * @throws \Masilia\Contracts\EditorialToolbox\Exception\TranslationMissingException
     */
    public function getOrCreateDraft(int $contentId, string $languageCode, ?TranslationRequest $translation = null): int;

    /**
     * Deletes the current user's draft for this language, if any.
     */
    public function discard(int $contentId, string $languageCode): void;
}
