<?php

declare(strict_types=1);

namespace Masilia\Tests\EditorialToolbox;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\TrashService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Ibexa\Core\Repository\Values\Content\Content as CoreContent;
use Ibexa\Core\Repository\Values\Content\ContentUpdateStruct;
use Ibexa\Core\Repository\Values\Content\Location as CoreLocation;
use Ibexa\Core\Repository\Values\Content\VersionInfo as CoreVersionInfo;
use Masilia\Contracts\EditorialToolbox\Event\BlockAddedEvent;
use Masilia\Contracts\EditorialToolbox\Event\BlockRemovedEvent;
use Masilia\Contracts\EditorialToolbox\Event\BlocksReorderedEvent;
use Masilia\EditorialToolbox\Block\PageBlocksService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PageBlocksServiceTest extends TestCase
{
    private const LOCATION_ID = 62;
    private const PAGE_ID = 10;

    private ContentService&MockObject $contentService;
    private LocationService&MockObject $locationService;
    private TrashService&MockObject $trashService;
    private EventDispatcherInterface $dispatcher;
    private PageBlocksService $service;

    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->contentService = $this->createMock(ContentService::class);
        $this->locationService = $this->createMock(LocationService::class);
        $this->trashService = $this->createMock(TrashService::class);

        $dispatcher = new EventDispatcher();
        foreach ([BlockAddedEvent::class, BlockRemovedEvent::class, BlocksReorderedEvent::class] as $class) {
            $dispatcher->addListener($class, function (object $event): void {
                $this->dispatched[] = $event;
            });
        }
        $this->dispatcher = $dispatcher;

        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturnCallback(
            static fn (string $name, ?string $namespace = null): mixed => match ($name) {
                'blocks_field' => 'blocks',
                'languages' => ['eng-GB'],
                default => null,
            }
        );

        $this->service = new PageBlocksService(
            $this->contentService,
            $this->createMock(ContentTypeService::class),
            $this->locationService,
            $this->trashService,
            $config,
            $this->dispatcher,
        );

        $this->locationService->method('loadLocation')->willReturnCallback(
            static fn (int $id): Location => new CoreLocation(['id' => $id, 'contentInfo' => self::contentInfo(self::PAGE_ID, $id)])
        );
        // The page (id 10) is on location 62; any other content is a block living on location 1000 + its id.
        $this->contentService->method('loadContent')->willReturnCallback(
            fn (int $id) => $id === self::PAGE_ID
                ? $this->pageContent([3, 4, 5])
                : $this->pageContent([], VersionInfo::STATUS_PUBLISHED, $id, 1000 + $id)
        );
        $this->contentService->method('newContentUpdateStruct')->willReturnCallback(static fn () => new ContentUpdateStruct());
    }

    public function testAddRelationInsertsAtPositionAndPublishes(): void
    {
        $saved = $this->expectPublishedWith();
        $this->contentService->method('createContentDraft')->willReturn($this->pageContent([3, 4, 5]));

        $this->service->addRelation(self::LOCATION_ID, 9, 1);

        self::assertSame([3, 9, 4, 5], $saved());
        self::assertInstanceOf(BlockAddedEvent::class, $this->dispatched[0]);
        self::assertSame(9, $this->dispatched[0]->getBlockContentId());
    }

    public function testAddRelationAppendsWhenPositionMissingOrOutOfRange(): void
    {
        $saved = $this->expectPublishedWith(2);
        $this->contentService->method('createContentDraft')->willReturn($this->pageContent([3, 4, 5]));

        $this->service->addRelation(self::LOCATION_ID, 9);
        self::assertSame([3, 4, 5, 9], $saved());

        $this->service->addRelation(self::LOCATION_ID, 8, 99);
        self::assertSame([3, 4, 5, 8], $saved());
    }

    public function testReorderKeepsOnlyCurrentlyAttachedIdsInRequestedOrder(): void
    {
        $saved = $this->expectPublishedWith();
        $this->contentService->method('createContentDraft')->willReturn($this->pageContent([3, 4, 5]));

        $this->service->reorder(self::LOCATION_ID, ['5', 3, 999, 4]);

        self::assertSame([5, 3, 4], $saved());
        self::assertInstanceOf(BlocksReorderedEvent::class, $this->dispatched[0]);
    }

    public function testRemoveDetachesTheBlockAndTrashesItsLocation(): void
    {
        $saved = $this->expectPublishedWith();
        $this->contentService->method('createContentDraft')->willReturn($this->pageContent([3, 4, 5]));

        $this->trashService->expects(self::once())->method('trash')
            ->with(self::callback(static fn (Location $location): bool => $location->id === 1004));

        $this->service->remove(self::LOCATION_ID, 4);

        self::assertSame([3, 5], $saved());
        self::assertInstanceOf(BlockRemovedEvent::class, $this->dispatched[0]);
    }

    public function testDraftIsDeletedAgainWhenPublishingFails(): void
    {
        $draft = $this->pageContent([3, 4, 5]);
        $this->contentService->method('createContentDraft')->willReturn($draft);
        $this->contentService->method('publishVersion')->willThrowException(new \RuntimeException('boom'));

        $this->contentService->expects(self::once())->method('deleteVersion')->with($draft->versionInfo);

        $this->expectException(\RuntimeException::class);
        $this->service->addRelation(self::LOCATION_ID, 9);
    }

    public function testDiscardBlockDraftOnlyDeletesDrafts(): void
    {
        $service = new PageBlocksService(
            $contentService = $this->createMock(ContentService::class),
            $this->createMock(ContentTypeService::class),
            $this->locationService,
            $this->trashService,
            $this->createMock(ConfigResolverInterface::class),
            $this->dispatcher,
        );

        $draft = $this->pageContent([], VersionInfo::STATUS_DRAFT);
        $published = $this->pageContent([], VersionInfo::STATUS_PUBLISHED);
        $contentService->method('loadContent')->willReturnOnConsecutiveCalls($draft, $published);
        $contentService->expects(self::once())->method('deleteVersion')->with($draft->versionInfo);

        $service->discardBlockDraft(1);
        $service->discardBlockDraft(2);
    }

    /**
     * Expects updateContent()+publishVersion() and returns a closure giving the ids of the last saved relation list.
     *
     * @return callable(): list<int>
     */
    private function expectPublishedWith(int $times = 1): callable
    {
        $last = [];
        $this->contentService->expects(self::exactly($times))->method('updateContent')->willReturnCallback(
            function ($versionInfo, ContentUpdateStruct $struct) use (&$last) {
                $value = $struct->fields[0]->value;
                self::assertInstanceOf(RelationListValue::class, $value);
                self::assertSame('blocks', $struct->fields[0]->fieldDefIdentifier);
                $last = $value->destinationContentIds;

                return $this->pageContent($last);
            }
        );
        $this->contentService->expects(self::exactly($times))->method('publishVersion');

        return static function () use (&$last): array {
            return $last;
        };
    }

    /**
     * @param list<int> $blockIds
     */
    private function pageContent(
        array $blockIds,
        int $status = VersionInfo::STATUS_DRAFT,
        int $contentId = self::PAGE_ID,
        int $mainLocationId = self::LOCATION_ID,
    ): Content {
        $versionInfo = new CoreVersionInfo([
            'contentInfo' => self::contentInfo($contentId, $mainLocationId),
            'status' => $status,
            'versionNo' => 2,
        ]);

        return new CoreContent([
            'versionInfo' => $versionInfo,
            'internalFields' => [
                new Field(['fieldDefIdentifier' => 'blocks', 'languageCode' => 'eng-GB', 'value' => new RelationListValue($blockIds)]),
            ],
        ]);
    }

    private static function contentInfo(int $id, int $mainLocationId): ContentInfo
    {
        return new ContentInfo([
            'id' => $id,
            'mainLocationId' => $mainLocationId,
            'mainLanguageCode' => 'eng-GB',
            'name' => 'Page',
        ]);
    }
}
