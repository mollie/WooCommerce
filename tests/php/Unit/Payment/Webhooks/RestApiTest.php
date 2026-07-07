<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Webhooks;

use Mockery;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi
 */
class RestApiTest extends TestCase
{
    /** @var Mockery\MockInterface&MollieOrderService */
    private $orderService;

    /** @var Mockery\MockInterface&LoggerInterface */
    private $logger;

    /** @var Mockery\MockInterface&WebhookTestService */
    private $webhookTestService;

    /** @var Mockery\MockInterface&WebhookSecret */
    private $webhookSecret;

    private RestApi $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = Mockery::mock(MollieOrderService::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->webhookTestService = Mockery::mock(WebhookTestService::class);
        $this->webhookSecret = Mockery::mock(WebhookSecret::class);
        $this->sut = new RestApi($this->orderService, $this->logger, $this->webhookTestService, $this->webhookSecret);
    }

    /**
     * @scenario checkWebhookSecret() delegates to the injected WebhookSecret
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::checkWebhookSecret
     */
    public function testCheckWebhookSecretDelegatesToWebhookSecret(): void
    {
        $this->webhookSecret->shouldReceive('check')->once()->with('incoming-token')->andReturn(true);

        $result = $this->sut->checkWebhookSecret('incoming-token');

        self::assertTrue($result);
    }

    /**
     * @scenario getOrCreateWebhookSecret() delegates to the injected WebhookSecret
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::getOrCreateWebhookSecret
     */
    public function testGetOrCreateWebhookSecretDelegatesToWebhookSecret(): void
    {
        $this->webhookSecret->shouldReceive('getOrCreate')->once()->andReturn('the-secret');

        $result = $this->sut->getOrCreateWebhookSecret();

        self::assertSame('the-secret', $result);
    }

    /**
     * @scenario registerRoutes() ensures the secret exists before registering the route,
     * so the route is never registered while the option is still empty.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function testRegisterRoutesEnsuresSecretExists(): void
    {
        $this->webhookSecret->shouldReceive('getOrCreate')->once()->andReturn('the-secret');

        $registered = null;
        \Brain\Monkey\Functions\when('register_rest_route')->alias(
            function (string $namespace, string $route, array $args) use (&$registered) {
                $registered = $args[0];
            }
        );

        $this->sut->registerRoutes();

        self::assertIsCallable($registered['permission_callback']);
    }
}