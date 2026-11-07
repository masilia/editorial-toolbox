<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\EventListener;

use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException as IbexaInvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\Contracts\EditorialToolbox\Exception\TranslationMissingException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns exceptions thrown by the toolbox API routes into JSON error responses.
 */
class JsonExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if (!str_starts_with($route, 'masilia_editorial_toolbox.')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof TranslationMissingException) {
            // Not an error to report but a question for the editor: the toolbar shows the choices.
            $event->setResponse(new JsonResponse([
                'error' => $exception->getMessage(),
                'code' => 'translation_missing',
                'language' => $exception->getLanguageCode(),
                'available' => $exception->getAvailableLanguages(),
            ], Response::HTTP_CONFLICT));

            return;
        }

        $status = match (true) {
            $exception instanceof UnauthorizedException => Response::HTTP_FORBIDDEN,
            $exception instanceof NotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof IbexaInvalidArgumentException,
            $exception instanceof \InvalidArgumentException,
            $exception instanceof \JsonException => Response::HTTP_BAD_REQUEST,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        $event->setResponse(new JsonResponse(['error' => $exception->getMessage()], $status));
    }
}
