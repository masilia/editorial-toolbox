<?php

declare(strict_types=1);

namespace Masilia\Contracts\EditorialToolbox\Translation;

/**
 * What the editor decided to do about a missing translation.
 */
final class TranslationRequest
{
    /** A new, empty translation. */
    public const BLANK = 'blank';
    /** A new translation pre-filled with the text of another language (what Ibexa's own "translate" does). */
    public const COPY = 'copy';
    /** A new translation pre-filled by a translation provider (Ibexa automated translation). */
    public const PROVIDER = 'provider';

    public const MODES = [self::BLANK, self::COPY, self::PROVIDER];

    public function __construct(
        public readonly string $mode,
        public readonly ?string $fromLanguage = null,
        public readonly ?string $provider = null,
    ) {
        if (!\in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown translation mode "%s", expected one of: %s.', $mode, implode(', ', self::MODES)));
        }

        if ($mode === self::PROVIDER && ($provider === null || $provider === '')) {
            throw new \InvalidArgumentException('The "provider" translation mode needs a provider.');
        }
    }

    /**
     * @param array<string, mixed> $data decoded JSON: {mode, from?, provider?}
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['mode'] ?? ''),
            isset($data['from']) && $data['from'] !== '' ? (string) $data['from'] : null,
            isset($data['provider']) && $data['provider'] !== '' ? (string) $data['provider'] : null,
        );
    }
}
