<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\EditorialToolbox\EventListener\CsrfValidationListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Hands the toolbar its CSRF token. It is served by a dedicated no-store endpoint instead of being printed in the
 * page, because pages are HTTP-cached per user *context*, not per session: an embedded token would be wrong
 * for every other editor that receives the cached copy.
 */
class CsrfTokenController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $tokenManager,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    public function token(): JsonResponse
    {
        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        $response = new JsonResponse(['token' => $this->tokenManager->getToken(CsrfValidationListener::TOKEN_ID)->getValue()]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
