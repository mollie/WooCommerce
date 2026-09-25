<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;

use function Brain\Monkey\Functions\when;

/**
 * Authentication guard on the legacy WC-API webhook (woocommerce_api_{gateway_id}).
 *
 * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
 */
class MollieOrderServiceWebhookAuthTest extends TestCase
{
    /** @var Mockery\MockInterface&HttpResponse */
    private $httpResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpResponse = Mockery::mock(HttpResponse::class);
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    private function makeService(WebhookSecret $secret): MollieOrderService
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->andReturn(null);

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(WebhookSecret::class)->andReturn($secret);

        return new MollieOrderService(
            $this->httpResponse,
            $logger,
            Mockery::mock(PaymentFactory::class),
            Mockery::mock(Data::class),
            'mollie-payments-for-woocommerce',
            $container,
            Mockery::mock(WebhookHandler::class)
        );
    }

    /**
     * @scenario A request with neither a valid mollie_webhook_secret nor an order_id + key
     * pair is rejected with HTTP 401 before any order lookup or processing takes place.
     */
    public function testRejectsRequestWithNeitherSecretNorOrderKey(): void
    {
        $this->httpResponse->shouldReceive('setHttpResponseCode')->with(401)->once();

        $service = $this->makeService(new WebhookSecret());

        // No mollie_webhook_secret and no order_id/key in the request.
        $service->onWebhookAction();

        // If we reached order processing, wc_get_orders would have been called and thrown
        // (it is not stubbed) - reaching the assertion proves the early 401 return.
        self::assertTrue(true);
    }

    /**
     * @scenario Backward compatibility: a webhook URL created before the secret existed has
     * no mollie_webhook_secret but does carry a valid order_id + key. It must pass the guard
     * so in-flight payments are not failed after an upgrade.
     */
    public function testInFlightOrderKeyPassesTheGuardWithoutSecret(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('key_is_valid')->with('wc_order_validkey')->andReturn(true);
        when('wc_get_order')->justReturn($order);

        // Tolerate whatever the downstream flow does; we only assert the guard's verdict.
        $this->httpResponse->shouldReceive('setHttpResponseCode')->andReturnNull();

        $service = $this->makeService(new WebhookSecret());

        $_GET['order_id'] = '89';
        $_GET['key'] = 'wc_order_validkey';
        $service->onWebhookAction();

        // A legitimate in-flight caller must NOT be rejected by the guard.
        $this->httpResponse->shouldNotHaveReceived('setHttpResponseCode', [401]);
        self::assertTrue(true);
    }

    /**
     * @scenario A caller that supplies an order_id but an invalid key (and no secret) is
     * rejected with HTTP 401 - the order key is a real per-order secret, not a free pass.
     */
    public function testInvalidOrderKeyIsRejected(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('key_is_valid')->andReturn(false);
        when('wc_get_order')->justReturn($order);

        $this->httpResponse->shouldReceive('setHttpResponseCode')->with(401)->once();

        $service = $this->makeService(new WebhookSecret());

        $_GET['order_id'] = '89';
        $_GET['key'] = 'wrong-key';
        $service->onWebhookAction();

        self::assertTrue(true);
    }

    /**
     * @scenario The testByMollie probe no longer returns a bare 200 to unauthenticated
     * callers - without a valid secret it is rejected with HTTP 401 like any other request.
     */
    public function testTestByMollieProbeIsRejectedWithoutSecret(): void
    {
        $this->httpResponse->shouldReceive('setHttpResponseCode')->with(401)->once();

        $service = $this->makeService(new WebhookSecret());

        $_GET['testByMollie'] = '';
        $service->onWebhookAction();

        self::assertTrue(true);
    }

    /**
     * @scenario A request carrying the correct secret passes the guard and proceeds to the
     * normal flow (here the testByMollie branch), without being rejected as unauthenticated.
     */
    public function testValidSecretPassesTheGuard(): void
    {
        $secret = 'test-webhook-secret-exactly-32cha';
        when('get_option')->justReturn($secret);

        // Tolerate whatever the downstream flow does; we only assert the guard's verdict.
        $this->httpResponse->shouldReceive('setHttpResponseCode')->andReturnNull();

        $service = $this->makeService(new WebhookSecret());

        $_GET['mollie_webhook_secret'] = $secret;
        $service->onWebhookAction();

        // The guard must NOT reject a caller with a valid secret.
        $this->httpResponse->shouldNotHaveReceived('setHttpResponseCode', [401]);
        self::assertTrue(true);
    }
}