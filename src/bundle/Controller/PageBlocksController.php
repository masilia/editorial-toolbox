<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\Controller;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Contracts\EditorialToolbox\Block\PageBlocksServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PageBlocksController
{
    public function __construct(
        private readonly PageBlocksServiceInterface $pageBlocks,
        private readonly PermissionResolver $permissionResolver,
        private readonly ContentTypeService $contentTypeService,
        private readonly LanguageService $languageService,
        private readonly ConfigResolverInterface $configResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function list(int $locationId): JsonResponse
    {
        // "edit", not "read": anonymous visitors can read content but must not enumerate editorial structure.
        if ($this->denied('edit')) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->pageBlocks->load($locationId)->toArray());
    }

    /**
     * Returns the Ibexa "content on the fly" create URL; the editor fills the form and publishes in the iframe.
     */
    public function create(int $locationId, Request $request): JsonResponse
    {
        if ($this->denied('create')) {
            return $this->accessDenied();
        }

        $data = $this->decode($request);
        $contentTypeIdentifier = $data['contentTypeIdentifier'] ?? null;
        if (!$contentTypeIdentifier) {
            return new JsonResponse(['error' => 'contentTypeIdentifier is required'], Response::HTTP_BAD_REQUEST);
        }

        $contentType = $this->contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);
        $languageCode = $this->configResolver->getParameter('languages')[0] ?? $this->languageService->getDefaultLanguageCode();

        $editUrl = $this->urlGenerator->generate('ibexa.content.on_the_fly.create', [
            'locationId' => $locationId,
            'languageCode' => $languageCode,
            'contentTypeIdentifier' => $contentTypeIdentifier,
            'siteaccess' => $this->configResolver->getParameter('admin_siteaccess', 'masilia_editorial_toolbox'),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse([
            'editUrl' => $editUrl,
            'position' => $data['position'] ?? null,
            'content_type' => $contentTypeIdentifier,
            'content_type_name' => $contentType->getName(),
        ], Response::HTTP_CREATED);
    }

    public function addRelation(int $locationId, Request $request): JsonResponse
    {
        if ($this->denied('edit')) {
            return $this->accessDenied();
        }

        $data = $this->decode($request);
        if (empty($data['contentId'])) {
            return new JsonResponse(['error' => 'contentId is required'], Response::HTTP_BAD_REQUEST);
        }

        $position = isset($data['position']) ? (int) $data['position'] : null;
        $this->pageBlocks->addRelation($locationId, (int) $data['contentId'], $position);

        return new JsonResponse(['success' => true]);
    }

    public function discardDraft(int $locationId, int $blockContentId): JsonResponse
    {
        if ($this->denied('edit')) {
            return $this->accessDenied();
        }

        $this->pageBlocks->discardBlockDraft($blockContentId);

        return new JsonResponse(['success' => true]);
    }

    public function remove(int $locationId, int $blockContentId): JsonResponse
    {
        if ($this->denied('remove')) {
            return $this->accessDenied();
        }

        $this->pageBlocks->remove($locationId, $blockContentId);

        return new JsonResponse(['success' => true]);
    }

    public function reorder(int $locationId, Request $request): JsonResponse
    {
        if ($this->denied('edit')) {
            return $this->accessDenied();
        }

        $data = $this->decode($request);
        $this->pageBlocks->reorder($locationId, $data['blockIds'] ?? []);

        return new JsonResponse(['success' => true]);
    }

    private function denied(string $function): bool
    {
        return $this->permissionResolver->hasAccess('content', $function) === false;
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return \is_array($data) ? $data : [];
    }
}
