<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Contracts\EditorialToolbox\Translation\TranslationOptionsServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TranslationController
{
    public function __construct(
        private readonly TranslationOptionsServiceInterface $options,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    /**
     * What the editor can do about the missing translation of a content: the languages to copy from and the providers able to
     * translate into the missing language (none when no provider is installed or configured).
     */
    public function options(int $contentId, string $languageCode): JsonResponse
    {
        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->options->options($contentId, $languageCode)->toArray());
    }
}
