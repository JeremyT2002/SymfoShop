<?php

declare(strict_types=1);

namespace App\Tests\Controller\Dev;

use App\Controller\Dev\DevPaymentSimulatorController;
use App\Repository\PaymentRepository;
use App\Service\Payment\OrderPaymentFinalizer;
use App\Service\Payment\PaymentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DevPaymentSimulatorAvailabilityTest extends TestCase
{
    public function testSimulatorIsHiddenInProduction(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller('prod', demoMode: false)->simulate('testbank_abc123', 'success');
    }

    public function testSimulatorWorksOnAPublicDemoInProduction(): void
    {
        // Gets past the availability check and looks the payment up (unknown here → back to the cart)
        $response = $this->controller('prod', demoMode: true)->simulate('testbank_abc123', 'success');

        self::assertSame('/cart', $response->headers->get('Location'));
    }

    private function controller(string $environment, bool $demoMode): DevPaymentSimulatorController
    {
        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('findOneByPaymentIntentId')->willReturn(null);

        $controller = new DevPaymentSimulatorController(
            $this->createMock(PaymentService::class),
            $payments,
            // final class; never reached in these tests
            (new \ReflectionClass(OrderPaymentFinalizer::class))->newInstanceWithoutConstructor(),
            $environment,
            $demoMode,
        );

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/cart');

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('router', $router);
        $controller->setContainer($container);

        return $controller;
    }
}
