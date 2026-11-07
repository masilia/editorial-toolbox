<?php

declare(strict_types=1);

namespace Masilia\EditorialToolbox\Translation;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationOptions;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationOptionsServiceInterface;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProviderInterface;

class TranslationOptionsService implements TranslationOptionsServiceInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly LanguageService $languageService,
        private readonly TranslationProviderInterface $provider,
    ) {
    }

    public function options(int $contentId, string $targetLanguage): TranslationOptions
    {
        $contentInfo = $this->contentService->loadContentInfo($contentId);
        $codes = $this->contentService->loadVersionInfo($contentInfo)->languageCodes;

        $available = array_map(fn (string $code): array => $this->describe($code), array_values($codes));

        // The language the content was written in is the natural source; else the first one it has.
        $default = \in_array($contentInfo->mainLanguageCode, $codes, true) ? $contentInfo->mainLanguageCode : (string) ($codes[0] ?? '');

        return new TranslationOptions(
            $this->describe($targetLanguage),
            $available,
            $default,
            $default === '' ? [] : $this->provider->providers($default, $targetLanguage),
        );
    }

    /**
     * @return array{code: string, name: string}
     */
    private function describe(string $code): array
    {
        try {
            $name = $this->languageService->loadLanguage($code)->name;
        } catch (\Exception) {
            $name = $code;
        }

        return ['code' => $code, 'name' => $name];
    }
}
