<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\InstanceRequestListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InstanceRequestListenerTest extends TestCase
{
    public function testHealthEndpointBypassesInstanceHeaderRequirement(): void
    {
        $listener = new InstanceRequestListener();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/notification/health', 'GET');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertFalse($request->attributes->has('instanceId'));
    }

    public function testRegularEndpointStillRequiresInstanceHeader(): void
    {
        $listener = new InstanceRequestListener();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/notification/inbox', 'GET');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame(
            '{"error":"Missing required X-Instance-Id header"}',
            $event->getResponse()->getContent(),
        );
    }
}
