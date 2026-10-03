<?php

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use WP_REST_Request;

/**
 * The REST webhook endpoint — the URL the plugin actually hands Mollie.
 *
 * UrlMiddleware::getWebhookUrl() builds the webhook URL from RestApi::ROUTE_NAMESPACE and only falls
 * back to the legacy /wc-api/ endpoint when the REST URL is unusable or a filter disables it. Every
 * test in WebhooksIntegrationTest drives that legacy fallback through
 * MollieOrderService::onWebhookAction(), so the entry point live traffic arrives on had no
 * integration coverage of its own.
 *
 * The two entry points converge at doPaymentForOrder(), which is where the handler behaviour is
 * pinned — this file covers only what RestApi implements separately: authentication of the incoming
 * request, resolving it to an order, and the HTTP status Mollie is answered with.
 */
class WebhookRestApiIntegrationTest extends IntegrationMockedTestCase
{
    use APIMockTrait;

    /**
     * The rest_api_init callbacks that were registered before this test isolated the hook.
     *
     * @var mixed
     */
    private $savedRestApiInit = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();

        // See WebhooksIntegrationTest: order notes are asserted in the source language, and a stale
        // methods transient silently overrides the mocked list of available gateways.
        if (function_exists('switch_to_locale')) {
            switch_to_locale('en_US');
        }
        unload_textdomain('mollie-payments-for-woocommerce');
        unload_textdomain('woocommerce');
        $this->flushMollieMethodsCache();
    }

    public function tearDown(): void
    {
        // Fixture orders are removed by IntegrationMockedTestCase::tearDown(); the REST server and
        // its route registrations are what this class has to put back itself.
        $this->restoreRestApiInit();

        if (function_exists('restore_previous_locale')) {
            restore_previous_locale();
        }

        parent::tearDown();
    }

    /**
     * Scenario: The webhook route is registered on the namespace the plugin publishes
     *   Given the plugin has booted and WordPress initialises its REST API
     *   Then a POST route exists at mollie/v1/webhook
     *
     * The webhook URL stored against every Mollie payment is built from these two constants. If the
     * route stops being registered — or the namespace changes — every in-flight payment webhook 404s
     * and orders silently stop updating, which is invisible from the plugin's own side.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function it_registers_the_webhook_route_mollie_sends_to()
    {
        $this->bootRestRoutes();

        $routes = rest_get_server()->get_routes();
        $route = '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE;

        $this->assertArrayHasKey(
            $route,
            $routes,
            'The webhook route named in every webhook URL the plugin issues must exist.'
        );

        $methods = [];
        foreach ($routes[$route] as $handler) {
            $methods += $handler['methods'] ?? [];
        }
        $this->assertArrayHasKey(
            'POST',
            $methods,
            'Mollie delivers webhooks by POST.'
        );
    }

    /**
     * Scenario: An anonymous caller cannot reach the webhook endpoint
     *   Given the webhook route is registered
     *   When a request arrives with neither the webhook secret nor a payment id that resolves to a
     *     known order
     *   Then WordPress rejects it with 401 and the callback never runs
     *
     * Driven through rest_do_request() rather than by calling the callback, because the guard lives
     * in the route's permission_callback: calling callback() directly would walk straight past it.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function it_rejects_an_unauthenticated_rest_webhook()
    {
        $order = $this->makePendingOrder();
        $orderId = $order->get_id();

        $this->bootRestRoutes();

        $request = new WP_REST_Request('POST', '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE);
        $request->set_param('id', 'tr_anunknownpayment');

        $response = rest_do_request($request);

        $this->assertSame(
            401,
            $response->get_status(),
            'A caller with no secret and no known payment must be refused by the permission callback.'
        );
        $this->assertEquals(
            'pending',
            wc_get_order($orderId)->get_status(),
            'A refused request must not have touched any order.'
        );
    }

    /**
     * Scenario: A secret-signed REST webhook completes the order
     *   Given a pending order tracked to its Mollie payment
     *   When Mollie POSTs that payment id to the REST endpoint with the site webhook secret
     *   Then the request is accepted with 200 and the order moves to processing
     *
     * This is the full production shape: the URL the plugin issues carries the secret and nothing
     * else, and the payment id arrives in the body. It is the path the vast majority of live webhook
     * traffic takes, and the only test that drives it end to end.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::callback
     */
    public function it_completes_an_order_from_a_secret_signed_rest_webhook()
    {
        $order = $this->makePendingOrder();
        $orderId = $order->get_id();
        $transactionId = $order->get_transaction_id();

        $this->mockSuccessfulPaymentGet($transactionId, 'paid', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $this->bootRestRoutes();

        $request = new WP_REST_Request('POST', '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE);
        $request->set_param('id', $transactionId);
        $request->set_param('mollie_webhook_secret', (new WebhookSecret())->getOrCreate());

        $response = rest_do_request($request);

        $this->assertSame(
            200,
            $response->get_status(),
            'An accepted webhook must be answered 200, or Mollie keeps retrying it.'
        );
        $this->assertEquals(
            'processing',
            wc_get_order($orderId)->get_status(),
            'A paid webhook delivered over REST must complete the order, exactly as the WC-API one does.'
        );
    }

    /**
     * Scenario: Mollie's own connectivity probe is answered without touching an order
     *   Given the webhook endpoint is reachable
     *   When Mollie calls it with its testByMollie probe and the site webhook secret
     *   Then it is answered 200 and no order is processed
     *
     * Mollie sends this when the merchant saves API keys, to check the URL is reachable. It carries
     * no payment, so anything that tried to resolve one would fail the probe and tell the merchant
     * their webhook is broken.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::callback
     */
    public function it_answers_the_mollie_connectivity_probe_without_processing_an_order()
    {
        $order = $this->makePendingOrder();
        $orderId = $order->get_id();

        $this->bootRestRoutes();

        $request = new WP_REST_Request('POST', '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE);
        $request->set_param('testByMollie', '');
        $request->set_param('mollie_webhook_secret', (new WebhookSecret())->getOrCreate());

        $response = rest_do_request($request);

        $this->assertSame(
            200,
            $response->get_status(),
            'The connectivity probe must be answered 200 or the merchant is told their webhook is broken.'
        );
        $this->assertEquals(
            'pending',
            wc_get_order($orderId)->get_status(),
            'The probe carries no payment and must not move any order.'
        );
    }

    /**
     * Scenario: A signed request with no payment id is a bad request
     *   Given a caller that authenticates with the webhook secret
     *   When it POSTs no payment id at all
     *   Then it is answered 404 rather than acting on a guess
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::callback
     */
    public function it_answers_404_when_the_rest_webhook_carries_no_payment_id()
    {
        $this->bootRestRoutes();

        $request = new WP_REST_Request('POST', '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE);
        $request->set_param('mollie_webhook_secret', (new WebhookSecret())->getOrCreate());

        $response = rest_do_request($request);

        $this->assertSame(
            404,
            $response->get_status(),
            'A webhook naming no payment must be reported as not found.'
        );
    }

    /**
     * Scenario: An ambiguous transaction id is refused rather than guessed
     *   Given two orders that both carry the same Mollie transaction id
     *   When a paid webhook for that id arrives over REST
     *   Then neither order is completed
     *
     * RestApi has its own copy of this lookup, so the guard covered on the WC-API side does not cover
     * this one. Answering 200 is deliberate: retrying will not make the ambiguity go away.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::callback
     */
    public function it_refuses_to_act_when_two_orders_share_a_transaction_id()
    {
        $sharedId = uniqid('tr_');
        $first = $this->makePendingOrder($sharedId);
        $second = $this->makePendingOrder($sharedId);

        $this->mockSuccessfulPaymentGet($sharedId, 'paid', [
            'metadata' => ['order_id' => $first->get_id()],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $this->bootRestRoutes();

        $request = new WP_REST_Request('POST', '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE);
        $request->set_param('id', $sharedId);
        $request->set_param('mollie_webhook_secret', (new WebhookSecret())->getOrCreate());

        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertEquals(
            'pending',
            wc_get_order($first->get_id())->get_status(),
            'An ambiguous transaction id must not complete the first matching order.'
        );
        $this->assertEquals(
            'pending',
            wc_get_order($second->get_id())->get_status(),
            'An ambiguous transaction id must not complete the second matching order.'
        );
    }

    /**
     * Scenario: A webhook for an unknown payment without a redirect URL is answered, not fatal
     *   Given a pending order tracked to a different Mollie payment
     *   And a Mollie payment that matches no order and was created without a redirectUrl
     *   When Mollie POSTs that payment id to the REST endpoint with the site webhook secret
     *   Then the request is answered 200 without a PHP fatal error
     *   And no order is processed
     *
     * With no matching order, RestApi::callback() falls back to reading the order id and key from the
     * payment's redirect URL. Payments created without one (recurring, API-created) carry a null
     * redirectUrl, which must not surface as a TypeError out of a string-typed accessor.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::callback
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::getRedirectUrlFromPaymentObject
     */
    public function it_answers_without_a_fatal_error_when_the_unmatched_payment_has_no_redirect_url()
    {
        $order = $this->makePendingOrder();
        $orderId = $order->get_id();
        $orphanTransactionId = 'tr_orphannoredirect';

        // The mocked API defaults redirectUrl to a return URL; null it as Mollie does for payments created without one.
        $this->mockSuccessfulPaymentGet($orphanTransactionId, 'paid', [
            'redirectUrl' => null,
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $this->bootRestRoutes();

        $request = new WP_REST_Request('POST', '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE);
        $request->set_param('id', $orphanTransactionId);
        $request->set_param('mollie_webhook_secret', (new WebhookSecret())->getOrCreate());

        $response = rest_do_request($request);

        $this->assertSame(
            200,
            $response->get_status(),
            'A webhook for a payment with no redirect URL must be answered, not crash the request.'
        );
        $this->assertEquals(
            'pending',
            wc_get_order($orderId)->get_status(),
            'An unmatched payment must not process any order.'
        );
    }

    /**
     * Boots the plugin against the mocked Mollie API and registers ONLY that container's REST routes.
     *
     * bootstrapModule() adds a rest_api_init listener bound to the container it just built and never
     * removes the previous one, so the listeners pile up across tests. register_rest_route() appends
     * handlers for a route it already knows rather than replacing them, and WP_REST_Server::dispatch()
     * runs the FIRST one it matches — the OLDEST container, whose mocked API knows nothing about this
     * test's payment. Isolating the hook is what makes the dispatched route the one just booted; the
     * previous callbacks are put back in tearDown() so no other test class is affected.
     */
    private function bootRestRoutes(): void
    {
        global $wp_rest_server;

        $this->savedRestApiInit = $GLOBALS['wp_filter']['rest_api_init'] ?? null;
        unset($GLOBALS['wp_filter']['rest_api_init']);

        $this->bootstrapModule($this->getMockedApiServices());

        // Force a fresh server so it collects routes from this container only.
        $wp_rest_server = null;
        rest_get_server();
    }

    /**
     * Puts the pre-existing rest_api_init callbacks back and drops the test's REST server.
     */
    private function restoreRestApiInit(): void
    {
        global $wp_rest_server;

        if ($this->savedRestApiInit !== null) {
            $GLOBALS['wp_filter']['rest_api_init'] = $this->savedRestApiInit;
        }
        $this->savedRestApiInit = null;
        $wp_rest_server = null;
    }

    /**
     * Builds a real UNPAID order at pending, tracked to a Payments API attempt.
     */
    private function makePendingOrder(string $transactionId = ''): \WC_Order
    {
        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false,
            $transactionId
        );

        $order->update_meta_data('_mollie_payment_id', $order->get_transaction_id());
        $order->set_status('pending');
        $order->save();

        return $order;
    }

    /**
     * Drops the cached Mollie methods list so the mocked fixture decides which gateways register.
     */
    private function flushMollieMethodsCache(): void
    {
        global $wpdb;

        $names = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%mollie-wc-%'"
        );

        foreach ($names as $name) {
            delete_transient(preg_replace('/^_transient_(timeout_)?/', '', $name));
        }
    }
}
