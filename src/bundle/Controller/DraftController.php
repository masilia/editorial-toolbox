<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Contracts\EditorialToolbox\Draft\InContextDraftManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DraftController
{
    public function __construct(
        private readonly InContextDraftManagerInterface $draftManager,
        private readonly PermissionResolver $permissionResolver,
        private readonly ConfigResolverInterface $configResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function edit(int $contentId, string $languageCode, int $locationId): JsonResponse
    {
        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        $versionNo = $this->draftManager->getOrCreateDraft($contentId, $languageCode);

        $editUrl = $this->urlGenerator->generate('ibexa.content.on_the_fly.edit', [
            'contentId' => $contentId,
            'versionNo' => $versionNo,
            'languageCode' => $languageCode,
            'locationId' => $locationId,
            'siteaccess' => $this->configResolver->getParameter('admin_siteaccess', 'masilia_editorial_toolbox'),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(['editUrl' => $editUrl]);
    }

    public function discard(int $contentId, string $languageCode): JsonResponse
    {
        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        $this->draftManager->discard($contentId, $languageCode);

        return new JsonResponse(['success' => true]);
    }
}
