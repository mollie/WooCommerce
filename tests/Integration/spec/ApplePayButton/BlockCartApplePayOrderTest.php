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
 * @group integration
 * @group ApplePayButton
 */
class BlockCartApplePayOrderTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const GATEWAY = 'mollie_wc_gateway_applepay';

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
            'token' => ['paymentData' => 'fake'],
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
