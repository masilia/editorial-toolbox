<?php

declare(strict_types=1);

namespace Masilia\EditorialToolbox\Meta;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Contracts\EditorialToolbox\Event\AdminMetaBuildEvent;
use Masilia\Contracts\EditorialToolbox\Meta\AdminMetaBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AdminMetaBuilder implements AdminMetaBuilderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ConfigResolverInterface $configResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function build(Content $content): array
    {
        $languages = $this->configResolver->getParameter('languages');
        $language = $languages[0] ?? $content->versionInfo->initialLanguageCode;

        $dashboardUrl = $this->urlGenerator->generate(
            'ibexa.dashboard',
            ['siteaccess' => $this->configResolver->getParameter('admin_siteaccess', 'masilia_editorial_toolbox')],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $meta = [
            'content_id' => $content->id,
            'remote_id' => $content->contentInfo->remoteId,
            'content_type' => $content->getContentType()->getName(),
            'location_id' => $content->contentInfo->mainLocationId,
            'dashboard_url' => $dashboardUrl,
            'current_language' => $language,
        ];

        $event = new AdminMetaBuildEvent($content, $meta);
        $this->eventDispatcher->dispatch($event);

        return $event->getMeta();
    }
}
