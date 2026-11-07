<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Draft;

interface InContextDraftManagerInterface
{
    /**
     * Returns the version number of the current user's draft for this language, creating it when missing.
     */
    public function getOrCreateDraft(int $contentId, string $languageCode): int;

    /**
     * Deletes the current user's draft for this language, if any.
     */
    public function discard(int $contentId, string $languageCode): void;
}
