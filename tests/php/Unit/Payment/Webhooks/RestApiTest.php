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
use WP_Error;
use WP_REST_Request;

use function Brain\Monkey\Functions\when;

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
     * @scenario A request carrying a valid mollie_webhook_secret is authorised.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function testPermissionAllowsRequestWithValidSecret(): void
    {
        $this->webhookSecret->shouldReceive('check')->with('the-secret')->andReturn(true);

        $callback = $this->permissionCallback();
        $result = $callback($this->request('the-secret', null));

        self::assertTrue($result);
    }

    /**
     * @scenario Backward compatibility: a pre-secret REST webhook URL is bare, so Mollie POSTs
     * only the transaction id. When that id resolves to an order we already know about, the
     * request is authorised so in-flight payments are not failed after an upgrade.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function testPermissionAllowsInFlightRequestWhenIdMatchesKnownOrder(): void
    {
        $this->webhookSecret->shouldReceive('check')->andReturn(false);
        when('wc_get_orders')->justReturn(['known-order']);

        $callback = $this->permissionCallback();
        $result = $callback($this->request(null, 'tr_knownpayment'));

        self::assertTrue($result);
    }

    /**
     * @scenario A well-formed id that matches no order (and no secret) is rejected.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function testPermissionRejectsWellFormedIdWithNoMatchingOrder(): void
    {
        $this->webhookSecret->shouldReceive('check')->andReturn(false);
        when('wc_get_orders')->justReturn([]);

        $callback = $this->permissionCallback();
        $result = $callback($this->request(null, 'tr_unknownpayment'));

        self::assertInstanceOf(WP_Error::class, $result);
    }

    /**
     * @scenario A malformed id (no tr_/ord_ prefix) is rejected without spending a DB lookup -
     * wc_get_orders is not stubbed, so calling it would fail the test.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function testPermissionRejectsMalformedIdWithoutLookup(): void
    {
        $this->webhookSecret->shouldReceive('check')->andReturn(false);

        $callback = $this->permissionCallback();
        $result = $callback($this->request(null, 'not-a-mollie-id'));

        self::assertInstanceOf(WP_Error::class, $result);
    }

    /**
     * @scenario Neither a secret nor an id is provided - the request is rejected.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function testPermissionRejectsWhenNeitherSecretNorId(): void
    {
        $this->webhookSecret->shouldReceive('check')->andReturn(false);

        $callback = $this->permissionCallback();
        $result = $callback($this->request(null, null));

        self::assertInstanceOf(WP_Error::class, $result);
    }

    /**
     * Register the route and return the captured permission_callback closure.
     */
    private function permissionCallback(): callable
    {
        $registered = null;
        when('register_rest_route')->alias(
            function (string $namespace, string $route, array $args) use (&$registered) {
                $registered = $args[0];
            }
        );
        $this->sut->registerRoutes();

        return $registered['permission_callback'];
    }

    private function request(?string $secret, ?string $id): WP_REST_Request
    {
        $request = Mockery::mock(WP_REST_Request::class);
        $request->shouldReceive('get_param')->with('mollie_webhook_secret')->andReturn($secret);
        $request->shouldReceive('get_param')->with('id')->andReturn($id);
        return $request;
    }
}