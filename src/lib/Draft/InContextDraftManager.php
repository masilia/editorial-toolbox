<?php

declare(strict_types=1);

namespace Masilia\EditorialToolbox\Draft;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Masilia\Contracts\EditorialToolbox\Draft\InContextDraftManagerInterface;
use Masilia\Contracts\EditorialToolbox\Exception\TranslationMissingException;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProviderInterface;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationRequest;

class InContextDraftManager implements InContextDraftManagerInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly LanguageService $languageService,
        private readonly PermissionResolver $permissionResolver,
        private readonly TranslationProviderInterface $translationProvider,
    ) {
    }

    public function getOrCreateDraft(int $contentId, string $languageCode, ?TranslationRequest $translation = null): int
    {
        $contentInfo = $this->contentService->loadContentInfo($contentId);

        $existing = $this->findOwnDraft($contentInfo, $languageCode);
        if ($existing !== null) {
            return $existing->versionNo;
        }

        $published = $this->contentService->loadVersionInfo($contentInfo);

        if (!\in_array($languageCode, $published->languageCodes, true)) {
            // Ibexa would happily create a draft in a language the content does not have (a blank translation, shown as an
            // empty form that can be published over what the page displays). It only happens when the editor chose it.
            if ($translation === null) {
                throw new TranslationMissingException($contentId, $languageCode, array_values($published->languageCodes));
            }

            return $this->createTranslationDraft($contentInfo, $published, $languageCode, $translation);
        }

        $content = $this->contentService->loadContent($contentId, [$languageCode]);
        $draft = $this->contentService->createContentDraft(
            $contentInfo,
            $content->getVersionInfo(),
            null,
            $this->languageService->loadLanguage($languageCode)
        );

        return $draft->getVersionInfo()->versionNo;
    }

    public function discard(int $contentId, string $languageCode): void
    {
        $contentInfo = $this->contentService->loadContentInfo($contentId);

        $draft = $this->findOwnDraft($contentInfo, $languageCode);
        if ($draft !== null) {
            $this->contentService->deleteVersion($draft);
        }
    }

    private function createTranslationDraft(ContentInfo $contentInfo, VersionInfo $published, string $to, TranslationRequest $request): int
    {
        if ($request->mode === TranslationRequest::BLANK) {
            return $this->createContentDraftIn($contentInfo, $published, $to);
        }

        $from = $request->fromLanguage ?? $contentInfo->mainLanguageCode;
        if (!\in_array($from, $published->languageCodes, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Content %d has no "%s" translation to copy from (it exists in: %s).',
                $contentInfo->id,
                $from,
                implode(', ', $published->languageCodes)
            ));
        }

        $source = $this->contentService->loadContent($contentInfo->id, [$from]);

        // Provider first: it is a network call, and a failure must leave nothing behind (no draft exists yet).
        $translated = $request->mode === TranslationRequest::PROVIDER
            ? $this->translationProvider->translateFields($source, $from, $to, (string) $request->provider)
            : [];

        // Ibexa's own recipe for a translation draft (see Ibexa\AutomatedTranslation\Translator::getTranslatedContent()): a draft
        // of the published version, then every translatable field set in the new language, to the translated value or, when
        // there is none, to the value of the source language. Non-translatable fields are shared and stay untouched.
        $draft = $this->contentService->createContentDraft($contentInfo);

        try {
            $struct = $this->contentService->newContentUpdateStruct();
            $struct->initialLanguageCode = $to;

            foreach ($source->getContentType()->getFieldDefinitions() as $definition) {
                if (!$definition->isTranslatable) {
                    continue;
                }

                $struct->setField(
                    $definition->identifier,
                    $translated[$definition->identifier] ?? $source->getFieldValue($definition->identifier, $from),
                    $to
                );
            }

            $this->contentService->updateContent($draft->versionInfo, $struct);
        } catch (\Throwable $e) {
            $this->deleteQuietly($draft->versionInfo);

            throw $e;
        }

        return $draft->versionInfo->versionNo;
    }

    private function createContentDraftIn(ContentInfo $contentInfo, VersionInfo $published, string $languageCode): int
    {
        $draft = $this->contentService->createContentDraft(
            $contentInfo,
            $published,
            null,
            $this->languageService->loadLanguage($languageCode)
        );

        return $draft->getVersionInfo()->versionNo;
    }

    private function deleteQuietly(VersionInfo $draft): void
    {
        try {
            $this->contentService->deleteVersion($draft);
        } catch (\Throwable) {
            // keep the original failure
        }
    }

    private function findOwnDraft(ContentInfo $contentInfo, string $languageCode): ?VersionInfo
    {
        $currentUserId = $this->permissionResolver->getCurrentUserReference()->getUserId();

        foreach ($this->contentService->loadVersions($contentInfo, VersionInfo::STATUS_DRAFT) as $version) {
            if ($version->creatorId === $currentUserId && $version->initialLanguageCode === $languageCode) {
                return $version;
            }
        }

        return null;
    }
}
