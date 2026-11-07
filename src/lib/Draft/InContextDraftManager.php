<?php

declare(strict_types=1);

namespace Masilia\EditorialToolbox\Draft;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Masilia\Contracts\EditorialToolbox\Draft\InContextDraftManagerInterface;

class InContextDraftManager implements InContextDraftManagerInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly LanguageService $languageService,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    public function getOrCreateDraft(int $contentId, string $languageCode): int
    {
        $contentInfo = $this->contentService->loadContentInfo($contentId);

        $existing = $this->findOwnDraft($contentInfo, $languageCode);
        if ($existing !== null) {
            return $existing->versionNo;
        }

        $content = $this->contentService->loadContent($contentId, [$languageCode]);

        // Ibexa would happily create a draft in a language the content does not have: a blank translation, shown in the editor
        // as an empty form that can be published over what the page displays. This tool edits existing text only.
        $languageCodes = $content->getVersionInfo()->languageCodes;
        if (!\in_array($languageCode, $languageCodes, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Content %d has no "%s" translation (it exists in: %s). The in-context editor edits existing text, it does not create translations.',
                $contentId,
                $languageCode,
                implode(', ', $languageCodes)
            ));
        }

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

    private function findOwnDraft(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo $contentInfo, string $languageCode): ?VersionInfo
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
