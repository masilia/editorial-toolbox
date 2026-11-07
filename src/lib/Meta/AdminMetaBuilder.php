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
            'current_language' => $this->displayedLanguage($content),
        ] + $this->translationState($content);

        $event = new AdminMetaBuildEvent($content, $meta);
        $this->eventDispatcher->dispatch($event);

        return $event->getMeta();
    }

    /**
     * `site_language` is the language of the siteaccess; `translation_missing` is true when the content has no text in it and is
     * therefore displayed through the fallback: the toolbar then asks the editor what to do instead of editing blindly.
     *
     * @return array{site_language: string|null, translation_missing: bool}
     */
    private function translationState(Content $content): array
    {
        $siteLanguage = $this->configResolver->getParameter('languages')[0] ?? null;

        return [
            'site_language' => $siteLanguage,
            'translation_missing' => $siteLanguage !== null && !\in_array($siteLanguage, $content->versionInfo->languageCodes, true),
        ];
    }

    /**
     * The language the content is displayed in: the first language of the siteaccess that the content really has, else its main
     * language (Ibexa's "always available" fallback). It is not simply the siteaccess language: a content that was never
     * translated is shown through the fallback, and asking for a draft in the siteaccess language would open a blank new
     * translation instead of the text the editor sees on the page.
     */
    private function displayedLanguage(Content $content): string
    {
        $available = $content->versionInfo->languageCodes;

        foreach ($this->configResolver->getParameter('languages') ?? [] as $code) {
            if (\in_array($code, $available, true)) {
                return $code;
            }
        }

        return $content->contentInfo->mainLanguageCode;
    }
}
