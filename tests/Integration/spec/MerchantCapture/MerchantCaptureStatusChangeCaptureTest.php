<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\MerchantCapture;

use Mockery;
use Mollie\Api\Endpoints\PaymentCaptureEndpoint;
use Mollie\WooCommerce\MerchantCapture\ManualCaptureStatus;
use Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use WC_Order;

/**
 * End-to-end proof that the 'Capture or cancel on status change' setting governs the
 * automatic capture-on-Completed for Klarna (Payments API) the same way it does for cards.
 *
 * Drives the real woocommerce_order_status transition through the module's disable filter
 * and PaymentModule::shipAndCaptureOrderAtMollie(), with only the Mollie API mocked.
 */
class MerchantCaptureStatusChangeCaptureTest extends IntegrationMockedTestCase
{
    use APIMockTrait;

    private const OPT_TEST_MODE = 'mollie-payments-for-woocommerce_test_mode_enabled';
    private const OPT_TEST_KEY = 'mollie-payments-for-woocommerce_test_api_key';
    private const OPT_ONHOLD = 'mollie-payments-for-woocommerce_place_payment_onhold';
    private const OPT_CAPTURE_OR_VOID = 'mollie-payments-for-woocommerce_capture_or_void';

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();

        update_option(self::OPT_TEST_MODE, 'yes');
        update_option(self::OPT_TEST_KEY, 'test_' . str_repeat('1', 32));

        // Gateway building during the full module boot resolves available payment methods.
        $this->apiMock()->getMockedApiClient()->methods
            ->shouldReceive('allAvailable')->andReturn([]);

        // Start from clean capture-relevant hooks so only this test's freshly booted
        // handlers run (WP/WooCommerce already fired these during wp-load, and prior tests
        // in the same process leave their own bootstrapped handlers behind).
        remove_all_actions('woocommerce_order_status_completed');
        remove_all_actions('woocommerce_order_status_changed');
        remove_all_filters('mollie_wc_gateway_disable_ship_and_capture');
    }

    /**
     * Scenario: Klarna, later-capture on, status-change capture off
     * Given an authorized Klarna payment and 'Capture or cancel on status change' unchecked
     * When the order status changes to Completed
     * Then no capture is sent to Mollie
     *
     * @test
     * @group integration
     * @group MerchantCapture
     * @covers \Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule::run
     */
    public function it_does_not_capture_klarna_on_completed_when_later_capture_on_and_status_change_off()
    {
        $this->configureCapture('later_capture', 'no');
        $captures = $this->expectNoCapture();

        $order = $this->makeAuthorizedOrder('mollie_wc_gateway_klarna', 'tr_klarna_no_capture');
        $this->bootAndInit();
        $order->update_status('completed');

        $captures->shouldNotHaveReceived('createForId');
    }

    /**
     * Scenario: Klarna, later-capture on, status-change capture on
     * Given an authorized Klarna payment and 'Capture or cancel on status change' checked
     * When the order status changes to Completed
     * Then the payment is captured at Mollie
     *
     * @test
     * @group integration
     * @group MerchantCapture
     * @covers \Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule::run
     */
    public function it_captures_klarna_on_completed_when_status_change_capture_on()
    {
        $this->configureCapture('later_capture', 'yes');
        $captures = $this->expectCapture();

        $order = $this->makeAuthorizedOrder('mollie_wc_gateway_klarna', 'tr_klarna_capture');
        $this->bootAndInit();
        $order->update_status('completed');

        $captures->shouldHaveReceived('createForId');
    }

    /**
     * Scenario: Klarna, default immediate-capture mode
     * Given an authorized Klarna payment and later-capture mode off (the default)
     * When the order status changes to Completed
     * Then the payment is still captured at Mollie - legacy behavior, no regression
     *
     * @test
     * @group integration
     * @group MerchantCapture
     * @covers \Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule::run
     */
    public function it_captures_klarna_on_completed_in_default_immediate_capture_mode()
    {
        $this->configureCapture('immediate_capture', 'no');
        $captures = $this->expectCapture();

        $order = $this->makeAuthorizedOrder('mollie_wc_gateway_klarna', 'tr_klarna_default');
        $this->bootAndInit();
        $order->update_status('completed');

        $captures->shouldHaveReceived('createForId');
    }

    /**
     * Scenario: credit card, later-capture on, status-change capture on
     * Given an authorized on-hold credit-card order and the setting checked
     * When the order status changes to Completed
     * Then it is captured (via the state-change path) and the legacy path stays disabled
     *
     * @test
     * @group integration
     * @group MerchantCapture
     * @covers \Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule::run
     */
    public function it_captures_creditcard_once_via_state_change_when_status_change_on()
    {
        $this->configureCapture('later_capture', 'yes');
        $captures = $this->expectCapture();

        $order = $this->makeAuthorizedOrder('mollie_wc_gateway_creditcard', 'tr_card_capture', true);
        $this->bootAndInit();
        $order->update_status('completed');

        $captures->shouldHaveReceived('createForId');
    }

    /**
     * Scenario: credit card, later-capture on, status-change capture off
     * Given an authorized on-hold credit-card order and the setting unchecked
     * When the order status changes to Completed
     * Then nothing is captured automatically - manual capture only
     *
     * @test
     * @group integration
     * @group MerchantCapture
     * @covers \Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule::run
     */
    public function it_does_not_capture_creditcard_on_completed_when_status_change_off()
    {
        $this->configureCapture('later_capture', 'no');
        $captures = $this->expectNoCapture();

        $order = $this->makeAuthorizedOrder('mollie_wc_gateway_creditcard', 'tr_card_no_capture', true);
        $this->bootAndInit();
        $order->update_status('completed');

        $captures->shouldNotHaveReceived('createForId');
    }

    private function configureCapture(string $onhold, string $captureOrVoid): void
    {
        update_option(self::OPT_ONHOLD, $onhold);
        update_option(self::OPT_CAPTURE_OR_VOID, $captureOrVoid);
    }

    /**
     * Boot the plugin container with the mocked Mollie API and attach the init-time hooks
     * (the disable filter and the state-change capture listener) that MerchantCaptureModule
     * registers on 'init'.
     */
    private function bootAndInit(): void
    {
        // Re-firing 'init' would re-run unrelated WooCommerce Blocks registrations and raise
        // duplicate-registration notices; suppress those and only run our module's init hooks.
        add_filter('doing_it_wrong_trigger_error', '__return_false');
        remove_all_actions('init');

        $this->bootstrapModule($this->getMockedApiServices());
        do_action('init');
    }

    /**
     * Create a paid-authorized order whose Mollie payment (tr_...) reports 'authorized'.
     * For credit card, also place it in the on-hold authorized state the manual-capture flow uses.
     */
    private function makeAuthorizedOrder(string $gateway, string $transactionId, bool $onHoldAuthorized = false): WC_Order
    {
        $this->mockSuccessfulPaymentGet($transactionId, 'authorized', [
            'method' => $gateway === 'mollie_wc_gateway_klarna' ? 'klarna' : 'creditcard',
            'mode' => 'test',
        ]);

        $order = $this->getConfiguredOrder(1, $gateway, ['simple'], [], false, $transactionId);
        $order->update_meta_data('_mollie_payment_id', $transactionId);

        if ($onHoldAuthorized) {
            $order->update_meta_data(
                MerchantCaptureModule::ORDER_PAYMENT_STATUS_META_KEY,
                ManualCaptureStatus::STATUS_AUTHORIZED
            );
            $order->save();
            $order->update_status('on-hold');
        }

        $order->save();

        return $order;
    }

    /**
     * @return \Mockery\MockInterface|PaymentCaptureEndpoint
     */
    private function expectCapture()
    {
        $captures = Mockery::spy(PaymentCaptureEndpoint::class);
        $captures->shouldReceive('createForId')->andReturn((object)['id' => 'cpt_test', 'status' => 'pending']);
        $this->apiMock()->getMockedApiClient()->paymentCaptures = $captures;

        return $captures;
    }

    /**
     * @return \Mockery\MockInterface|PaymentCaptureEndpoint
     */
    private function expectNoCapture()
    {
        $captures = Mockery::spy(PaymentCaptureEndpoint::class);
        $this->apiMock()->getMockedApiClient()->paymentCaptures = $captures;

        return $captures;
    }
}
