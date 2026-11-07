<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\EventListener;

use Ibexa\ContentForms\Event\FormActionEvent;
use Ibexa\Contracts\AdminUi\Event\ContentOnTheFlyEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * After a content-on-the-fly create/edit publish, answer with a page that tells the parent window
 * (the front-end toolbar hosting the iframe) that the work is done.
 */
class ContentOnTheFlyResponseListener implements EventSubscriberInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ContentOnTheFlyEvents::CONTENT_CREATE_PUBLISH => ['onCreatePublish', 5],
            ContentOnTheFlyEvents::CONTENT_EDIT_PUBLISH => ['onEditPublish', 5],
        ];
    }

    public function onCreatePublish(FormActionEvent $event): void
    {
        $this->respond($event, '@ibexadesign/masilia_editorial_toolbox/on_the_fly/content_create_response.html.twig');
    }

    public function onEditPublish(FormActionEvent $event): void
    {
        $this->respond($event, '@ibexadesign/masilia_editorial_toolbox/on_the_fly/content_edit_response.html.twig');
    }

    private function respond(FormActionEvent $event, string $template): void
    {
        /** @var \Ibexa\Contracts\Core\Repository\Values\Content\Content|null $content */
        $content = $event->getPayload('content');
        if ($content === null) {
            return;
        }

        $event->setResponse(new Response($this->twig->render($template, [
            'locationId' => $content->contentInfo->mainLocationId,
            'contentId' => $content->id,
        ])));
    }
}
