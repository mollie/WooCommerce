<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common;

use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\Doubles\RecordingLogger;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\FakeMollieApi;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\FakeMollieTransport;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\InMemoryStore;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\SessionRules;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Harness for the Express Component / Checkout Sessions flow.
 *
 * It differs from PaymentFlowTestCase in where Mollie is faked. There the SDK client is a Mockery
 * double; here the plugin runs its real Api helper, the real SDK and the real HTTP adapter, and a
 * FakeMollieApi answers at the WordPress HTTP layer. The Sessions flow needs that: the call is a
 * raw performHttpCall(), the payment is one the plugin never created, the idempotency key is a
 * header, and the error text that must not leak is assembled by the adapter.
 *
 * No Mollie API key is involved. The keys, the webhook secret and the shopper data the harness
 * installs are CanaryData values, so assertNothingLeaked*() can prove none of them reaches a log
 * line or a browser-bound response.
 *
 * A scenario reads, top to bottom:
 *
 *     $container = $this->bootExpress();
 *     $this->cartWith(['simple']);
 *     $response = $this->restRequest('POST', '/mollie/v1/express/session', [...]);
 *     $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid']);
 *     $status = $this->deliverWebhook($payment['id']);
 *     $this->assertNothingLeakedToLog();
 */
abstract class ExpressFlowTestCase extends PaymentFlowTestCase
{
    protected const PLUGIN_ID = 'mollie-payments-for-woocommerce';

    private FakeMollieApi $fakeMollie;

    private FakeMollieTransport $transport;

    private RecordingLogger $recordingLogger;

    /**
     * The rest_api_init hooks as they were before bootExpress() cleared them, restored in tearDown().
     *
     * @var mixed
     */
    private $restApiInitBackup = null;

    private bool $restApiInitCleared = false;

    private int $userBackup = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->fakeMollie = new FakeMollieApi(new InMemoryStore());
        $this->transport = new FakeMollieTransport($this->fakeMollie);
        $this->transport->install();
        $this->recordingLogger = new RecordingLogger();
        $this->userBackup = get_current_user_id();

        $this->setOptionForTest(self::PLUGIN_ID . '_live_api_key', CanaryData::LIVE_API_KEY);
        $this->setOptionForTest(self::PLUGIN_ID . '_test_api_key', CanaryData::TEST_API_KEY);
        $this->setOptionForTest('mollie_webhook_secret', CanaryData::WEBHOOK_SECRET);
        $this->useLiveMode();

        $this->forgetApiClient();
        // The methods list the gateways register from now comes from the fake.
        $this->flushMollieMethodsCache();
    }

    public function tearDown(): void
    {
        $this->transport->uninstall();
        $this->forgetApiClient();
        $this->emptyCart();
        wp_set_current_user($this->userBackup);

        if ($this->restApiInitCleared) {
            if ($this->restApiInitBackup === null) {
                unset($GLOBALS['wp_filter']['rest_api_init']);
            } else {
                $GLOBALS['wp_filter']['rest_api_init'] = $this->restApiInitBackup;
            }
            $GLOBALS['wp_rest_server'] = null;
            $this->restApiInitCleared = false;
        }

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Booting the plugin
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Boots the plugin against the fake Mollie, with the recording logger in place of the real one,
     * and rebuilds the REST server so the routes this boot registered are the ones that answer.
     *
     * Every bootstrap adds another generation of rest_api_init callbacks bound to its own
     * container. Left in place, a route would be served by whichever generation registered first,
     * so they are cleared before booting and restored in tearDown().
     *
     * @param array<string, callable> $serviceOverrides
     */
    protected function bootExpress(array $serviceOverrides = []): ContainerInterface
    {
        if (!$this->restApiInitCleared) {
            $this->restApiInitBackup = $GLOBALS['wp_filter']['rest_api_init'] ?? null;
            $this->restApiInitCleared = true;
        }
        unset($GLOBALS['wp_filter']['rest_api_init']);

        $logger = $this->recordingLogger;
        $container = $this->bootstrapModule(array_merge([
            LoggerInterface::class => static function () use ($logger): LoggerInterface {
                return $logger;
            },
        ], $serviceOverrides));

        \WC_Payment_Gateways::instance()->init();

        $GLOBALS['wp_rest_server'] = null;
        rest_get_server();

        return $container;
    }

    /**
     * Live mode with a fake live key. The default, because the Sessions beta may have no test mode
     * and the feature may therefore be gated on it (REQ-H4). Safe only because the transport
     * answers every call to api.mollie.com itself.
     */
    protected function useLiveMode(): void
    {
        $this->setOptionForTest(self::PLUGIN_ID . '_test_mode_enabled', 'no');
        $this->forgetApiClient();
    }

    protected function useTestMode(): void
    {
        $this->setOptionForTest(self::PLUGIN_ID . '_test_mode_enabled', 'yes');
        $this->forgetApiClient();
    }

    /**
     * Api keeps its client in a process-wide static, keyed to nothing. A client built for an
     * earlier test — or a Mockery double left by another test class — would otherwise be reused.
     */
    private function forgetApiClient(): void
    {
        $client = new \ReflectionProperty(Api::class, 'api_client');
        $client->setAccessible(true);
        $client->setValue(null, null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The shopper
    // ──────────────────────────────────────────────────────────────────────────

    protected function actAsGuest(): void
    {
        wp_set_current_user(0);
    }

    protected function actAsCustomer(?int $customerId = null): void
    {
        wp_set_current_user($customerId ?? $this->customer_id);
    }

    /**
     * Puts products in a real WooCommerce cart, the state an express checkout starts from.
     *
     * The cart and its session are not loaded on a CLI request, and adding to the cart tries to
     * set cookies after PHPUnit has already produced output. Both are handled here so a scenario
     * can simply say which products the shopper has.
     *
     * @param array<int, string> $presets Names from ProductPresets, one entry per cart line.
     */
    protected function cartWith(array $presets, int $quantity = 1): \WC_Cart
    {
        $this->loadCart();
        WC()->cart->empty_cart();

        foreach ($presets as $preset) {
            // IntegrationMockedTestCase::setUp() created every preset product already.
            $productId = (int) wc_get_product_id_by_sku(ProductPresets::get()[$preset]['sku']);
            $this->assertGreaterThan(0, $productId, "The '{$preset}' preset product does not exist.");
            $this->withoutCookieWarnings(static function () use ($productId, $quantity): void {
                WC()->cart->add_to_cart($productId, $quantity);
            });
        }
        WC()->cart->calculate_totals();

        return WC()->cart;
    }

    protected function emptyCart(): void
    {
        if (function_exists('WC') && WC()->cart instanceof \WC_Cart) {
            WC()->cart->empty_cart();
        }
    }

    private function loadCart(): void
    {
        if (!WC()->cart instanceof \WC_Cart) {
            $this->withoutCookieWarnings(static function (): void {
                wc_load_cart();
            });
        }
    }

    /**
     * wc_setcookie() raises a notice when headers are already sent, which PHPUnit turns into a
     * failure. Under CLI they always are; there is no browser to receive the cookie anyway.
     */
    private function withoutCookieWarnings(callable $callback): void
    {
        set_error_handler(static function (int $severity, string $message): bool {
            return strpos($message, 'headers already sent') !== false
                || strpos($message, 'cannot be set') !== false;
        }, E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);

        try {
            $callback();
        } finally {
            restore_error_handler();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Entry points
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Drives a REST route through the real server, so permission_callback, argument validation and
     * the status code are all part of what is observed. Call bootExpress() first.
     *
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     */
    protected function restRequest(string $method, string $route, array $params = [], array $headers = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, $route);
        foreach ($params as $name => $value) {
            $request->set_param($name, $value);
        }
        foreach ($headers as $name => $value) {
            $request->set_header($name, $value);
        }

        return rest_do_request($request);
    }

    /**
     * Mollie calling the webhook for a payment, the way it really does: only the id in the body,
     * the secret in the URL. Returns the status code, which is the whole contract with Mollie's
     * retry system. Call bootExpress() first.
     */
    protected function deliverWebhook(string $paymentId, bool $withSecret = true): int
    {
        $params = ['id' => $paymentId];
        if ($withSecret) {
            $params['mollie_webhook_secret'] = CanaryData::WEBHOOK_SECRET;
        }

        return $this->restRequest(
            'POST',
            '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE,
            $params
        )->get_status();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Observation
    // ──────────────────────────────────────────────────────────────────────────

    protected function fakeMollie(): FakeMollieApi
    {
        return $this->fakeMollie;
    }

    protected function logger(): RecordingLogger
    {
        return $this->recordingLogger;
    }

    /**
     * The bodies of every POST /v2/sessions the plugin sent, in order.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sessionPayloads(): array
    {
        return array_map(static function (array $request): array {
            return (array) $request['body'];
        }, $this->fakeMollie->requests('POST', 'sessions'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Assertions
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * REQ-G6 / FF-16: no API key, webhook secret or shopper detail in anything that was logged,
     * at any level, with debug logging on.
     */
    protected function assertNothingLeakedToLog(): void
    {
        $this->assertFalse(
            CanaryData::leakedIn($this->recordingLogger->dump()),
            "A marked secret or personal detail reached the log:\n" . $this->recordingLogger->dump()
        );
    }

    /**
     * REQ-G1: nothing identifying the Mollie account, and no shopper data, in what the browser gets.
     *
     * @param mixed $browserBound Response data about to cross the wire.
     */
    protected function assertNothingLeakedToBrowser($browserBound): void
    {
        $serialised = (string) wp_json_encode($browserBound);

        $this->assertFalse(
            CanaryData::leakedIn($serialised),
            'A marked secret or personal detail is in a browser-bound response: ' . $serialised
        );
        $this->assertStringNotContainsString('pfl_', $serialised, 'A Mollie profile id is in a browser-bound response.');
    }

    /**
     * The payload satisfies the documented Sessions rules. The fake already answers 422 when it
     * does not; this states the reason in the test failure instead of in an exception message.
     *
     * @param array<string, mixed> $payload
     */
    protected function assertValidSessionPayload(array $payload): void
    {
        $violations = SessionRules::violations($payload);

        $this->assertSame([], $violations, 'The session payload breaks the documented rules: ' . wp_json_encode($violations));
    }
}
