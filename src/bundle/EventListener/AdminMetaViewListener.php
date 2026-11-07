<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\EventListener;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\Event\PreContentViewEvent;
use Ibexa\Core\MVC\Symfony\MVCEvents;
use Ibexa\Core\MVC\Symfony\View\ContentView;
use Masilia\Contracts\EditorialToolbox\Meta\AdminMetaBuilderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Exposes the `admin_meta` template parameter to users who are allowed to edit content.
 */
class AdminMetaViewListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly AdminMetaBuilderInterface $adminMetaBuilder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [MVCEvents::PRE_CONTENT_VIEW => 'onPreContentView'];
    }

    public function onPreContentView(PreContentViewEvent $event): void
    {
        $view = $event->getContentView();
        if (!$view instanceof ContentView) {
            return;
        }

        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return;
        }

        $content = $view->getContent();
        if ($content === null) {
            return;
        }

        $view->addParameters(['admin_meta' => $this->adminMetaBuilder->build($content)]);
    }
}
