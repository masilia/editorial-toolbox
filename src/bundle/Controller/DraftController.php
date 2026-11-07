<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Contracts\EditorialToolbox\Draft\InContextDraftManagerInterface;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * Optional JSON body: {"translation": {"mode": "blank|copy|provider", "from": "eng-GB", "provider": "deepl"}}, the editor's
     * answer when the content has no translation in $languageCode (see the 409 "translation_missing" answer).
     */
    public function edit(int $contentId, string $languageCode, int $locationId, Request $request): JsonResponse
    {
        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        $translation = null;
        if ($request->getContent() !== '') {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (\is_array($body) && isset($body['translation']) && \is_array($body['translation'])) {
                $translation = TranslationRequest::fromArray($body['translation']);
            }
        }

        $versionNo = $this->draftManager->getOrCreateDraft($contentId, $languageCode, $translation);

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
