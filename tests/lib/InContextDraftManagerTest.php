<?php

declare(strict_types=1);

namespace Masilia\Tests\EditorialToolbox;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference;
use Ibexa\Core\FieldType\TextLine\Value as TextLine;
use Ibexa\Core\Repository\Values\Content\Content as CoreContent;
use Ibexa\Core\Repository\Values\Content\ContentUpdateStruct;
use Ibexa\Core\Repository\Values\Content\VersionInfo as CoreVersionInfo;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\Contracts\EditorialToolbox\Exception\TranslationMissingException;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationProviderInterface;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationRequest;
use Masilia\EditorialToolbox\Draft\InContextDraftManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class InContextDraftManagerTest extends TestCase
{
    private const ME = 14;

    private ContentService&MockObject $contentService;
    private TranslationProviderInterface&MockObject $provider;
    private InContextDraftManager $manager;
    private ContentInfo $contentInfo;

    protected function setUp(): void
    {
        $this->contentInfo = new ContentInfo(['id' => 62, 'mainLanguageCode' => 'eng-GB']);
        $this->contentService = $this->createMock(ContentService::class);
        $this->contentService->method('loadContentInfo')->willReturn($this->contentInfo);
        $this->contentService->method('newContentUpdateStruct')->willReturnCallback(static fn () => new ContentUpdateStruct());

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

        $this->provider = $this->createMock(TranslationProviderInterface::class);

        $this->manager = new InContextDraftManager(
            $this->contentService,
            $this->createMock(LanguageService::class),
            $permissionResolver,
            $this->provider,
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

    public function testCreatesADraftWhenTheContentHasTheLanguageAndNoneMatches(): void
    {
        $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([$this->draft(3, 99, 'eng-GB')]);
        $this->contentService->method('loadContent')->willReturn(new CoreContent(['versionInfo' => $this->published(['eng-GB'])]));
        $this->contentService->expects(self::once())->method('createContentDraft')
            ->willReturn(new CoreContent(['versionInfo' => $this->draft(7, self::ME, 'eng-GB')]));

        self::assertSame(7, $this->manager->getOrCreateDraft(62, 'eng-GB'));
    }

    public function testAMissingTranslationWithoutADecisionIsAQuestionNotABlankDraft(): void
    {
        // The content only exists in English; the siteaccess language is French (it is shown through the fallback).
        $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([]);
        $this->contentService->expects(self::never())->method('createContentDraft');

        try {
            $this->manager->getOrCreateDraft(62, 'fre-FR');
            self::fail('A TranslationMissingException was expected.');
        } catch (TranslationMissingException $e) {
            self::assertSame(62, $e->getContentId());
            self::assertSame('fre-FR', $e->getLanguageCode());
            self::assertSame(['eng-GB'], $e->getAvailableLanguages());
        }
    }

    public function testBlankCreatesAnEmptyTranslationDraftWithoutCopyingAnything(): void
    {
        $published = $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([]);
        $this->contentService->expects(self::once())->method('createContentDraft')
            ->with($this->contentInfo, $published, null, self::anything())
            ->willReturn(new CoreContent(['versionInfo' => $this->draft(8, self::ME, 'fre-FR')]));
        $this->contentService->expects(self::never())->method('updateContent');

        self::assertSame(8, $this->manager->getOrCreateDraft(62, 'fre-FR', new TranslationRequest(TranslationRequest::BLANK)));
    }

    public function testCopyPrefillsTheTranslatableFieldsFromTheSourceLanguageOnly(): void
    {
        $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([]);
        $this->contentService->method('loadContent')->willReturn($this->source());

        $draft = new CoreContent(['versionInfo' => $this->draft(9, self::ME, 'eng-GB')]);
        $this->contentService->expects(self::once())->method('createContentDraft')->with($this->contentInfo)->willReturn($draft);
        $this->contentService->expects(self::once())->method('updateContent')->with(
            $draft->versionInfo,
            self::callback(function (ContentUpdateStruct $struct): bool {
                self::assertSame('fre-FR', $struct->initialLanguageCode);
                $set = [];
                foreach ($struct->fields as $field) {
                    self::assertSame('fre-FR', $field->languageCode);
                    $set[$field->fieldDefIdentifier] = (string) $field->value;
                }
                // 'reference' is not translatable: shared by every language, so it is not touched.
                self::assertSame(['title' => 'Header', 'phone' => '+212 1'], $set);

                return true;
            })
        );

        self::assertSame(9, $this->manager->getOrCreateDraft(62, 'fre-FR', new TranslationRequest(TranslationRequest::COPY)));
    }

    public function testProviderTranslatesWhatItCanAndKeepsTheSourceForTheRest(): void
    {
        $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([]);
        $source = $this->source();
        $this->contentService->method('loadContent')->willReturn($source);

        $this->provider->expects(self::once())->method('translateFields')
            ->with($source, 'eng-GB', 'fre-FR', 'deepl')
            ->willReturn(['title' => new TextLine('En-tête')]);

        $draft = new CoreContent(['versionInfo' => $this->draft(10, self::ME, 'eng-GB')]);
        $this->contentService->method('createContentDraft')->willReturn($draft);
        $this->contentService->expects(self::once())->method('updateContent')->with(
            $draft->versionInfo,
            self::callback(function (ContentUpdateStruct $struct): bool {
                $set = [];
                foreach ($struct->fields as $field) {
                    $set[$field->fieldDefIdentifier] = (string) $field->value;
                }
                self::assertSame(['title' => 'En-tête', 'phone' => '+212 1'], $set);

                return true;
            })
        );

        $request = new TranslationRequest(TranslationRequest::PROVIDER, 'eng-GB', 'deepl');
        self::assertSame(10, $this->manager->getOrCreateDraft(62, 'fre-FR', $request));
    }

    public function testAProviderFailureLeavesNoDraftBehind(): void
    {
        $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([]);
        $this->contentService->method('loadContent')->willReturn($this->source());
        $this->provider->method('translateFields')->willThrowException(new \RuntimeException('quota exceeded'));

        $this->contentService->expects(self::never())->method('createContentDraft');

        $this->expectException(\RuntimeException::class);
        $this->manager->getOrCreateDraft(62, 'fre-FR', new TranslationRequest(TranslationRequest::PROVIDER, 'eng-GB', 'deepl'));
    }

    public function testTheDraftIsDeletedAgainWhenFillingItFails(): void
    {
        $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([]);
        $this->contentService->method('loadContent')->willReturn($this->source());

        $draft = new CoreContent(['versionInfo' => $this->draft(11, self::ME, 'eng-GB')]);
        $this->contentService->method('createContentDraft')->willReturn($draft);
        $this->contentService->method('updateContent')->willThrowException(new \RuntimeException('validation failed'));

        $this->contentService->expects(self::once())->method('deleteVersion')->with($draft->versionInfo);

        $this->expectException(\RuntimeException::class);
        $this->manager->getOrCreateDraft(62, 'fre-FR', new TranslationRequest(TranslationRequest::COPY));
    }

    public function testCopyFromALanguageTheContentDoesNotHaveIsRejected(): void
    {
        $this->publishedIn(['eng-GB']);
        $this->contentService->method('loadVersions')->willReturn([]);
        $this->contentService->expects(self::never())->method('createContentDraft');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no "ger-DE" translation to copy from.*exists in: eng-GB/');

        $this->manager->getOrCreateDraft(62, 'fre-FR', new TranslationRequest(TranslationRequest::COPY, 'ger-DE'));
    }

    public function testDiscardDeletesOnlyTheCurrentUsersDraftForThatLanguage(): void
    {
        $mine = $this->draft(5, self::ME, 'eng-GB');
        $this->contentService->method('loadVersions')->willReturn([$this->draft(3, 99, 'eng-GB'), $mine]);
        $this->contentService->expects(self::once())->method('deleteVersion')->with($mine);

        $this->manager->discard(62, 'eng-GB');
    }

    /**
     * @param string[] $languages
     */
    private function published(array $languages): VersionInfo
    {
        return new CoreVersionInfo([
            'contentInfo' => $this->contentInfo,
            'versionNo' => 1,
            'languageCodes' => $languages,
            'status' => VersionInfo::STATUS_PUBLISHED,
        ]);
    }

    /**
     * @param string[] $languages
     */
    private function publishedIn(array $languages): VersionInfo
    {
        $published = $this->published($languages);
        $this->contentService->method('loadVersionInfo')->willReturn($published);

        return $published;
    }

    /**
     * An English-only content with three fields: two translatable (title, phone) and one shared (reference).
     */
    private function source(): CoreContent
    {
        $contentType = $this->createMock(ContentType::class);
        $contentType->method('getFieldDefinitions')->willReturn(new FieldDefinitionCollection([
            new FieldDefinition(['identifier' => 'title', 'isTranslatable' => true]),
            new FieldDefinition(['identifier' => 'reference', 'isTranslatable' => false]),
            new FieldDefinition(['identifier' => 'phone', 'isTranslatable' => true]),
        ]));

        return new CoreContent([
            'versionInfo' => $this->published(['eng-GB']),
            'contentType' => $contentType,
            'internalFields' => [
                new Field(['fieldDefIdentifier' => 'title', 'languageCode' => 'eng-GB', 'value' => new TextLine('Header')]),
                new Field(['fieldDefIdentifier' => 'reference', 'languageCode' => 'eng-GB', 'value' => new TextLine('ref-1')]),
                new Field(['fieldDefIdentifier' => 'phone', 'languageCode' => 'eng-GB', 'value' => new TextLine('+212 1')]),
            ],
        ]);
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
