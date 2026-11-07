<?php

declare(strict_types=1);

namespace Masilia\Tests\Bundle\EditorialToolbox;

use Masilia\Bundle\EditorialToolbox\EventListener\CsrfValidationListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfValidationListenerTest extends TestCase
{
    public function testSafeMethodsAndOtherRoutesAreNotChecked(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method('isTokenValid');

        $listener = new CsrfValidationListener($manager);

        self::assertNull($this->dispatch($listener, 'GET', 'masilia_editorial_toolbox.blocks.list')->getResponse());
        self::assertNull($this->dispatch($listener, 'POST', 'some_other_route')->getResponse());
    }

    public function testStateChangingRequestWithoutTokenIsRejected(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method('isTokenValid');

        $response = $this->dispatch(new CsrfValidationListener($manager), 'DELETE', 'masilia_editorial_toolbox.blocks.remove')->getResponse();

        self::assertSame(403, $response?->getStatusCode());
    }

    public function testInvalidTokenIsRejectedAndValidTokenPasses(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => $token->getId() === CsrfValidationListener::TOKEN_ID && $token->getValue() === 'good'
        );
        $listener = new CsrfValidationListener($manager);

        self::assertSame(403, $this->dispatch($listener, 'POST', 'masilia_editorial_toolbox.draft.edit', 'bad')->getResponse()?->getStatusCode());
        self::assertNull($this->dispatch($listener, 'POST', 'masilia_editorial_toolbox.draft.edit', 'good')->getResponse());
    }

    private function dispatch(CsrfValidationListener $listener, string $method, string $route, ?string $token = null): RequestEvent
    {
        $request = Request::create('/x', $method, server: $token !== null ? ['HTTP_X_CSRF_TOKEN' => $token] : []);
        $request->attributes->set('_route', $route);

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onRequest($event);

        return $event;
    }
}
