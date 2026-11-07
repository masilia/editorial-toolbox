<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Requires a valid `X-CSRF-Token` header on every state-changing request to a toolbox route.
 */
class CsrfValidationListener implements EventSubscriberInterface
{
    public const TOKEN_ID = 'masilia_editorial_toolbox';

    public function __construct(private readonly CsrfTokenManagerInterface $tokenManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // After the router (32) has set _route, before the controller is resolved.
        return [KernelEvents::REQUEST => ['onRequest', 8]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if (!str_starts_with($route, 'masilia_editorial_toolbox.') || $request->isMethodSafe()) {
            return;
        }

        $token = (string) $request->headers->get('X-CSRF-Token', '');

        if ($token === '' || !$this->tokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN));
        }
    }
}
