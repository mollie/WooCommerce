<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ApplePayButton;

use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Psr\Container\ContainerInterface;

/**
 * The classic Apple Pay button on the block cart and the block checkout, from the nonce the page
 * receives to the order WooCommerce creates.
 *
 * This test runs the real checkout with the nonce the page gets.
 * It starts at process_checkout(): the handler reads the request with filter_input(), which has
 * no input under PHPUnit.
 *
 * process_checkout() defines WOOCOMMERCE_CHECKOUT, which makes is_checkout() true for the rest of
 * the process, so this folder runs as the last suite (tests/Integration/phpunit.xml.dist). Every
 * test that runs process_checkout() belongs here.
 *
 * @group integration
 * @group ApplePayButton
 */
class BlockCartApplePayOrderTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const GATEWAY = 'mollie_wc_gateway_applepay';

    /** What Apple hands the page after the shopper authorised. */
    private const APPLE_TOKEN = ['paymentData' => 'fake'];

    /**
     * @var array<string, mixed>
     */
    private array $postBackup = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpExpressCheckout();
        $this->postBackup = ['post' => $_POST, 'request' => $_REQUEST];
    }

    public function tearDown(): void
    {
        $_POST = $this->postBackup['post'];
        $_REQUEST = $this->postBackup['request'];
        $this->tearDownExpressCheckout();
        parent::tearDown();
    }

    /**
     * Scenario: the button pays from a block page with the nonce that page was given
     *   Given a guest with a virtual product in the cart
     *   And the block page's Apple Pay data carries a nonce
     *   When the button posts the authorized payment with that nonce
     *   Then WooCommerce creates the order for the Apple Pay gateway
     *   And WooCommerce answers success with the Mollie redirect
     *
     * @test
     */
    public function it_creates_the_order_when_the_block_page_nonce_is_posted(): void
    {
        $container = $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->makeCartVirtual();
        $before = $this->allOrderIds();

        $answer = $this->postAuthorizedPayment($this->nonceOfTheBlockPage($container));

        $created = array_values(array_diff($this->allOrderIds(), $before));
        $this->assertCount(1, $created, 'The button must create one order. Answer: ' . wp_json_encode($answer));
        $this->assertSame(self::GATEWAY, wc_get_order($created[0])->get_payment_method());
        $this->assertSame('success', $answer['result'] ?? null, 'Answer: ' . wp_json_encode($answer));
    }

    /**
     * Scenario: the same payment with the checkout nonce of a classic page (control)
     *   Given a guest with a virtual product in the cart
     *   When the button posts the authorized payment with a woocommerce-process_checkout nonce
     *   Then WooCommerce creates the order for the Apple Pay gateway
     *
     * It shows that the cart, the payload and the gateway are fine, so the test above fails for the nonce only.
     *
     * @test
     */
    public function it_creates_the_order_with_the_classic_checkout_nonce(): void
    {
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->makeCartVirtual();
        $before = $this->allOrderIds();

        $answer = $this->postAuthorizedPayment(wp_create_nonce('woocommerce-process_checkout'));

        $created = array_values(array_diff($this->allOrderIds(), $before));
        $this->assertCount(1, $created, 'Answer: ' . wp_json_encode($answer));
        $this->assertSame(self::GATEWAY, wc_get_order($created[0])->get_payment_method());
    }

    /**
     * Scenario: a nonce nobody issued still creates no order
     *   Given a guest with a virtual product in the cart
     *   When the button posts the authorized payment with a made-up nonce
     *   Then no order is created
     *
     * @test
     */
    public function it_creates_no_order_for_a_nonce_nobody_issued(): void
    {
        $container = $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->makeCartVirtual();
        $before = $this->allOrderIds();

        $this->postAuthorizedPayment('abcdef1234');

        $this->assertSame([], array_values(array_diff($this->allOrderIds(), $before)));
    }

    /**
     * Scenario: the token Apple returned reaches Mollie with the payment
     *   Given a guest with a virtual product in the cart
     *   And the shop creates its payments through the given Mollie API
     *   When the button posts the authorized payment with the token Apple returned
     *   Then Mollie is asked for one Apple Pay payment carrying that token, encoded
     *
     * @test
     * @dataProvider mollieApis
     * @group REQ-318
     */
    public function it_sends_the_apple_pay_token_to_mollie(string $api, string $endpoint, array $tokenPath): void
    {
        // Given
        $this->setOptionForTest(self::PLUGIN_ID . '_api_switch', $api);
        $container = $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->makeCartVirtual();

        // When
        $answer = $this->postAuthorizedPayment($this->nonceOfTheBlockPage($container));

        // Then
        $created = $this->fakeMollie()->requests('POST', $endpoint);
        $this->assertCount(1, $created, 'One Mollie ' . $endpoint . ' request. Answer: ' . wp_json_encode($answer));
        $sent = $created[0]['body'];
        $this->assertSame('applepay', $sent['method']);
        $token = $sent;
        foreach ($tokenPath as $key) {
            $token = $token[$key] ?? null;
        }
        $this->assertSame(wp_json_encode(self::APPLE_TOKEN), $token);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    public function mollieApis(): array
    {
        return [
            'Payments API' => ['payment', 'payments', ['applePayPaymentToken']],
            'Orders API' => ['order', 'orders', ['payment', 'applePayPaymentToken']],
        ];
    }

    /**
     * Scenario: Mollie's notification decides what happens to the Apple Pay order
     *   Given a guest paid a virtual product with the Apple Pay button
     *   When Mollie reports the payment with the given status
     *   Then the webhook is answered 200
     *   And the order gets the given status, paid only when Mollie said paid
     *
     * @test
     * @dataProvider mollieOutcomes
     * @group REQ-341
     */
    public function it_settles_the_apple_pay_order_from_the_webhook(string $mollieStatus, string $wcStatus, bool $paid): void
    {
        // Given
        $this->setOptionForTest(self::PLUGIN_ID . '_api_switch', 'payment');
        $container = $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->makeCartVirtual();
        $before = $this->allOrderIds();
        $answer = $this->postAuthorizedPayment($this->nonceOfTheBlockPage($container));
        $created = array_values(array_diff($this->allOrderIds(), $before));
        $this->assertCount(1, $created, 'Answer: ' . wp_json_encode($answer));
        $paymentId = (string) wc_get_order($created[0])->get_meta('_mollie_payment_id', true);
        $this->assertStringStartsWith('tr_', $paymentId);

        // When
        $this->fakeMollie()->setPaymentStatus($paymentId, $mollieStatus);
        $status = $this->deliverWebhook($paymentId);

        // Then
        $this->assertSame(200, $status);
        $order = wc_get_order($created[0]);
        $this->assertSame($wcStatus, $order->get_status());
        $this->assertSame($paid, $order->is_paid());
        $this->assertSame(self::GATEWAY, $order->get_payment_method());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public function mollieOutcomes(): array
    {
        return [
            'paid' => ['paid', 'processing', true],
            'failed' => ['failed', 'failed', false],
            'canceled' => ['canceled', 'pending', false],
            'expired' => ['expired', 'cancelled', false],
        ];
    }

    /**
     * Scenario: the order is paid when Mollie charges the Apple Pay token at creation
     *   Given a guest paid a virtual product with the Apple Pay button
     *   And the shop creates its payments through the given Mollie API
     *   And Mollie answers the creation with the payment already paid, as it does for a token
     *   And Mollie's webhook arrives at the given moment
     *   When the webhook is processed
     *   Then the webhook is answered 200
     *   And the order is paid with the Apple Pay gateway
     *
     * The webhook before the creation answer is the race seen live on 2026-10-26 (QA beta 7 #23):
     * Mollie calls while the plugin has not linked the payment to the order yet. On the Orders API
     * Mollie answered a token order with no redirectUrl and the method creditcard.
     *
     * @test
     * @dataProvider tokenCharges
     * @group REQ-341
     */
    public function it_pays_the_order_when_mollie_charges_the_token_at_creation(string $api, bool $webhookBeforeTheAnswer): void
    {
        // Given
        $this->setOptionForTest(self::PLUGIN_ID . '_api_switch', $api);
        $container = $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->makeCartVirtual();
        $before = $this->allOrderIds();
        $mollieId = '';
        $early = null;
        $paidAtCreation = function ($response, array $args, string $url) use ($webhookBeforeTheAnswer, &$mollieId, &$early) {
            if (!is_array($response) || ($args['method'] ?? '') !== 'POST' || !preg_match('#/v2/(payments|orders)$#', $url)) {
                return $response;
            }
            $created = json_decode((string) $response['body'], true);
            $mollieId = (string) $created['id'];
            if (strpos($mollieId, 'ord_') === 0) {
                $orders = $this->fakeMollie()->orders();
                $this->fakeMollie()->setPaymentStatus((string) $orders[$mollieId]['paymentId'], 'paid');
                $this->fakeMollie()->setOrderStatus($mollieId, 'paid', ['redirectUrl' => null, 'method' => 'creditcard']);
                $created = array_merge($created, ['redirectUrl' => null, 'method' => 'creditcard']);
            } else {
                $this->fakeMollie()->setPaymentStatus($mollieId, 'paid');
            }
            $created['status'] = 'paid';
            $response['body'] = wp_json_encode($created);
            if ($webhookBeforeTheAnswer) {
                // The plugin has not seen the creation answer yet: the order does not know the payment.
                $early = $this->deliverWebhook($mollieId);
            }

            return $response;
        };
        add_filter('pre_http_request', $paidAtCreation, 2, 3);
        try {
            $answer = $this->postAuthorizedPayment($this->nonceOfTheBlockPage($container));
        } finally {
            remove_filter('pre_http_request', $paidAtCreation, 2);
        }
        $created = array_values(array_diff($this->allOrderIds(), $before));
        $this->assertCount(1, $created, 'Answer: ' . wp_json_encode($answer));

        // When
        $status = $early ?? $this->deliverWebhook($mollieId);

        // Then
        $this->assertSame(200, $status);
        $order = wc_get_order($created[0]);
        $this->assertTrue($order->is_paid(), 'Status: ' . $order->get_status());
        $this->assertSame(self::GATEWAY, $order->get_payment_method());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function tokenCharges(): array
    {
        return [
            'Payments API, webhook after the creation answer' => ['payment', false],
            'Payments API, webhook before the creation answer' => ['payment', true],
            'Orders API, webhook after the creation answer' => ['order', false],
            'Orders API, webhook before the creation answer' => ['order', true],
        ];
    }

    private function nonceOfTheBlockPage(ContainerInterface $container): string
    {
        $methods = $container->get('gateway.paymentMethods');
        $this->assertArrayHasKey('applepay', $methods, 'Apple Pay must be a registered payment method.');
        $data = $methods['applepay']->blocksData($container);

        return (string) ($data['expressButtonData']['nonce'] ?? '');
    }

    private function makeCartVirtual(): void
    {
        foreach (WC()->cart->get_cart() as $item) {
            $item['data']->set_virtual(true);
        }
        WC()->cart->calculate_totals();
    }

    /**
     * What ApplePayButtonComponent.js posts on mollie_apple_pay_create_order_cart, through the real
     * checkout. WooCommerce ends in wp_send_json(), which is caught here as the answer.
     *
     * @return array<string, mixed>
     */
    private function postAuthorizedPayment(string $nonce): array
    {
        $contact = [
            'givenName' => 'Ada',
            'familyName' => 'Lovelace',
            'emailAddress' => 'ada@example.com',
            'addressLines' => ['Teststraat 1', ''],
            'postalCode' => '1011AB',
            'locality' => 'Amsterdam',
            'administrativeArea' => '',
            'countryCode' => 'NL',
            'phoneNumber' => '0612345678',
        ];
        $_POST = [
            'action' => 'mollie_apple_pay_create_order_cart',
            'shippingContact' => $contact,
            'billingContact' => $contact,
            'token' => self::APPLE_TOKEN,
            'shippingMethod' => [],
            'mollie-payments-for-woocommerce_issuer_applepay' => 'applepay',
            'woocommerce-process-checkout-nonce' => $nonce,
            'billing_first_name' => 'Ada',
            'billing_last_name' => 'Lovelace',
            'billing_company' => '',
            'billing_country' => 'NL',
            'billing_address_1' => 'Teststraat 1',
            'billing_address_2' => '',
            'billing_postcode' => '1011AB',
            'billing_city' => 'Amsterdam',
            'billing_state' => '',
            'billing_phone' => '0612345678',
            'billing_email' => 'ada@example.com',
            'shipping_first_name' => 'Ada',
            'shipping_last_name' => 'Lovelace',
            'shipping_company' => '',
            'shipping_country' => 'NL',
            'shipping_address_1' => 'Teststraat 1',
            'shipping_address_2' => '',
            'shipping_postcode' => '1011AB',
            'shipping_city' => 'Amsterdam',
            'shipping_state' => '',
            'shipping_phone' => '0612345678',
            'shipping_email' => 'ada@example.com',
            'order_comments' => '',
            'payment_method' => self::GATEWAY,
            '_wp_http_referer' => '/?wc-ajax=update_order_review',
        ];
        $_REQUEST = array_merge($_REQUEST, $_POST);

        // AppleAjaxRequests::createWcOrderFromCart() runs its own nonce check and then this call.
        return $this->answerOf(static function (): void {
            WC()->checkout()->process_checkout();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function answerOf(callable $handler): array
    {
        $die = static function (): callable {
            return static function (): void {
                // An Error: WooCommerce's checkout catches every Exception and would answer a second time.
                throw new \Error('wp_die');
            };
        };
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $die, 99);
        add_filter('wp_die_handler', $die, 99);
        ob_start();
        try {
            $handler();
        } catch (\Error $e) {
            // The handler answered and stopped, as it does on a real request.
        } finally {
            $output = (string) ob_get_clean();
            remove_filter('wp_doing_ajax', '__return_true');
            remove_filter('wp_die_ajax_handler', $die, 99);
            remove_filter('wp_die_handler', $die, 99);
        }
        $start = strpos($output, '{');
        $decoded = $start === false ? null : json_decode(substr($output, $start), true);

        return is_array($decoded) ? $decoded : ['raw' => $output];
    }
}
