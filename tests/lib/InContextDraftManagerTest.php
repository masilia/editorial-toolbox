<?php

declare(strict_types=1);

namespace Masilia\Tests\EditorialToolbox;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference;
use Ibexa\Core\Repository\Values\Content\Content as CoreContent;
use Ibexa\Core\Repository\Values\Content\VersionInfo as CoreVersionInfo;
use Masilia\EditorialToolbox\Draft\InContextDraftManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class InContextDraftManagerTest extends TestCase
{
    private const ME = 14;

    private ContentService&MockObject $contentService;
    private InContextDraftManager $manager;
    private ContentInfo $contentInfo;

    protected function setUp(): void
    {
        $this->contentInfo = new ContentInfo(['id' => 62, 'mainLanguageCode' => 'eng-GB']);
        $this->contentService = $this->createMock(ContentService::class);
        $this->contentService->method('loadContentInfo')->willReturn($this->contentInfo);

        $permissionResolver = $this->createMock(PermissionResolver::class);
        $permissionResolver->method('getCurrentUserReference')->willReturn(new class(self::ME) implements UserReference {
            public function __construct(private int $id)
            {
            }

            public function getUserId(): int
            {
                return $this->id;
            }
        });

        $this->manager = new InContextDraftManager(
            $this->contentService,
            $this->createMock(LanguageService::class),
            $permissionResolver,
        );
    }

    public function testReusesTheCurrentUsersDraftForTheSameLanguage(): void
    {
        $this->contentService->method('loadVersions')->willReturn([
            $this->draft(3, 99, 'eng-GB'),   // someone else's
            $this->draft(4, self::ME, 'fre-FR'), // other language
            $this->draft(5, self::ME, 'eng-GB'), // the one we want
        ]);
        $this->contentService->expects(self::never())->method('createContentDraft');

        self::assertSame(5, $this->manager->getOrCreateDraft(62, 'eng-GB'));
    }

    public function testCreatesADraftWhenNoneMatches(): void
    {
        $this->contentService->method('loadVersions')->willReturn([$this->draft(3, 99, 'eng-GB')]);
        $this->contentService->method('loadContent')->willReturn(new CoreContent(['versionInfo' => $this->draft(1, 1, 'eng-GB')]));
        $this->contentService->expects(self::once())->method('createContentDraft')
            ->willReturn(new CoreContent(['versionInfo' => $this->draft(7, self::ME, 'eng-GB')]));

        self::assertSame(7, $this->manager->getOrCreateDraft(62, 'eng-GB'));
    }

    public function testRefusesToCreateABlankTranslation(): void
    {
        // The content only exists in English; the siteaccess language is French (it is shown through the fallback).
        $this->contentService->method('loadVersions')->willReturn([]);
        $this->contentService->method('loadContent')->willReturn(new CoreContent(['versionInfo' => $this->draft(1, 1, 'eng-GB')]));
        $this->contentService->expects(self::never())->method('createContentDraft');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no "fre-FR" translation.*exists in: eng-GB/');

        $this->manager->getOrCreateDraft(62, 'fre-FR');
    }

    public function testDiscardDeletesOnlyTheCurrentUsersDraftForThatLanguage(): void
    {
        $mine = $this->draft(5, self::ME, 'eng-GB');
        $this->contentService->method('loadVersions')->willReturn([$this->draft(3, 99, 'eng-GB'), $mine]);
        $this->contentService->expects(self::once())->method('deleteVersion')->with($mine);

        $this->manager->discard(62, 'eng-GB');
    }

    private function draft(int $versionNo, int $creatorId, string $language): VersionInfo
    {
        return new CoreVersionInfo([
            'contentInfo' => $this->contentInfo,
            'versionNo' => $versionNo,
            'creatorId' => $creatorId,
            'initialLanguageCode' => $language,
            'languageCodes' => ['eng-GB'],
            'status' => VersionInfo::STATUS_DRAFT,
        ]);
    }
}
