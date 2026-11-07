<?php

declare(strict_types=1);

namespace Masilia\Tests\Bundle\EditorialToolbox;

use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Base\Exceptions\UnauthorizedException;
use Masilia\Bundle\EditorialToolbox\EventListener\JsonExceptionListener;
use Masilia\Contracts\EditorialToolbox\Exception\TranslationMissingException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class JsonExceptionListenerTest extends TestCase
{
    /**
     * @dataProvider cases
     */
    public function testMapsExceptionsToStatusCodes(\Throwable $exception, int $expectedStatus): void
    {
        $event = $this->event('masilia_editorial_toolbox.blocks.list', $exception);

        (new JsonExceptionListener())->onException($event);

        self::assertNotNull($event->getResponse());
        self::assertSame($expectedStatus, $event->getResponse()->getStatusCode());
        self::assertSame(['error' => $exception->getMessage()], json_decode($event->getResponse()->getContent(), true));
    }

    /**
     * @return iterable<string, array{\Throwable, int}>
     */
    public static function cases(): iterable
    {
        yield 'not found' => [new NotFoundException('content', 12), 404];
        yield 'unauthorized' => [new UnauthorizedException('content', 'edit'), 403];
        yield 'bad json' => [new \JsonException('Syntax error'), 400];
        yield 'invalid argument' => [new \InvalidArgumentException('nope'), 400];
        yield 'anything else' => [new \RuntimeException('boom'), 500];
    }

    public function testAMissingTranslationIsAQuestionForTheToolbarNotAnError(): void
    {
        $event = $this->event('masilia_editorial_toolbox.draft.edit', new TranslationMissingException(239, 'fre-FR', ['eng-GB']));

        (new JsonExceptionListener())->onException($event);

        self::assertSame(409, $event->getResponse()->getStatusCode());
        $body = json_decode($event->getResponse()->getContent(), true);
        self::assertSame('translation_missing', $body['code']);
        self::assertSame('fre-FR', $body['language']);
        self::assertSame(['eng-GB'], $body['available']);
    }

    public function testIgnoresOtherRoutes(): void
    {
        $event = $this->event('some_other_route', new \RuntimeException('boom'));

        (new JsonExceptionListener())->onException($event);

        self::assertNull($event->getResponse());
    }

    private function event(string $route, \Throwable $exception): ExceptionEvent
    {
        $request = new Request(attributes: ['_route' => $route]);

        return new ExceptionEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
