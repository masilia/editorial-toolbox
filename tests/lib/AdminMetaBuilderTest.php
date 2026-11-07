<?php

declare(strict_types=1);

namespace Masilia\Tests\EditorialToolbox;

use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\Repository\Values\Content\Content as CoreContent;
use Ibexa\Core\Repository\Values\Content\VersionInfo as CoreVersionInfo;
use Masilia\Contracts\EditorialToolbox\Event\AdminMetaBuildEvent;
use Masilia\EditorialToolbox\Meta\AdminMetaBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminMetaBuilderTest extends TestCase
{
    public function testBuildsTheMetaPayloadUsingTheConfiguredAdminSiteaccess(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')
            ->with('ibexa.dashboard', ['siteaccess' => 'admin_mattcch'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://admin.example/dashboard');

        $meta = $this->builder($urls, new EventDispatcher())->build($this->content());

        self::assertSame([
            'content_id' => 62,
            'remote_id' => 'home',
            'content_type' => 'Home Page',
            'location_id' => 62,
            'dashboard_url' => 'http://admin.example/dashboard',
            'current_language' => 'fre-FR',
        ], $meta);
    }

    public function testListenersCanExtendTheMeta(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('#');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AdminMetaBuildEvent::class, static function (AdminMetaBuildEvent $event): void {
            $event->setMeta($event->getMeta() + ['extra' => $event->getContent()->id]);
        });

        self::assertSame(62, $this->builder($urls, $dispatcher)->build($this->content())['extra']);
    }

    private function builder(UrlGeneratorInterface $urls, EventDispatcher $dispatcher): AdminMetaBuilder
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturnCallback(
            static fn (string $name, ?string $namespace = null): mixed => match ($name) {
                'languages' => ['fre-FR', 'eng-GB'],
                'admin_siteaccess' => 'admin_mattcch',
                default => null,
            }
        );

        return new AdminMetaBuilder($urls, $config, $dispatcher);
    }

    private function content(): CoreContent
    {
        $contentType = $this->createMock(ContentType::class);
        $contentType->method('getName')->willReturn('Home Page');

        $contentInfo = new ContentInfo(['id' => 62, 'remoteId' => 'home', 'mainLocationId' => 62, 'mainLanguageCode' => 'eng-GB']);

        return new CoreContent([
            'versionInfo' => new CoreVersionInfo(['contentInfo' => $contentInfo, 'initialLanguageCode' => 'eng-GB']),
            'contentType' => $contentType,
        ]);
    }
}
