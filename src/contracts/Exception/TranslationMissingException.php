<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Exception;

/**
 * The content has no translation in the requested language and nobody said what to do about it. The API turns this into a
 * "409 translation_missing" answer so the toolbar can ask the editor.
 */
final class TranslationMissingException extends \RuntimeException
{
    /**
     * @param string[] $availableLanguages
     */
    public function __construct(
        private readonly int $contentId,
        private readonly string $languageCode,
        private readonly array $availableLanguages,
    ) {
        parent::__construct(sprintf(
            'Content %d has no "%s" translation (it exists in: %s). Say whether to create a blank translation, copy one or translate it with a provider.',
            $contentId,
            $languageCode,
            implode(', ', $availableLanguages)
        ));
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    /**
     * @return string[]
     */
    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }
}
