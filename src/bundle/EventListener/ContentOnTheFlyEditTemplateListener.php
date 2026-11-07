<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\EventListener;

use Ibexa\AdminUi\View\EditContentOnTheFlyView;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ibexa hardcodes the "content on the fly" edit template, and a bundle cannot override a template that
 * another bundle ships. Swap the template of the view instead, before Ibexa renders it, so the form
 * can talk to the toolbar hosting the iframe (publish / cancel / fullscreen messages).
 */
class ContentOnTheFlyEditTemplateListener implements EventSubscriberInterface
{
    public const TEMPLATE = '@ibexadesign/masilia_editorial_toolbox/on_the_fly/content_edit_form.html.twig';

    public static function getSubscribedEvents(): array
    {
        // Ibexa's ViewRendererListener runs at the default priority 0.
        return [KernelEvents::VIEW => ['onView', 10]];
    }

    public function onView(ViewEvent $event): void
    {
        $view = $event->getControllerResult();

        if ($view instanceof EditContentOnTheFlyView) {
            $view->setTemplateIdentifier(self::TEMPLATE);
        }
    }
}
