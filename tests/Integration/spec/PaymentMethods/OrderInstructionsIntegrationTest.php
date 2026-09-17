<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\PaymentMethods;

use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use ReflectionProperty;
use WC_Order;

/**
 * Drives the real WooCommerce email hooks that render the Mollie payment instructions, against a
 * mocked Mollie API. The unit test for OrderInstructionsManager calls displayInstructions()
 * directly with a mocked payment; this one goes through the wiring the reported bug actually
 * travelled: gateway registration on `woocommerce_after_register_post_type`, the
 * `woocommerce_email_after_order_table` hook, and the real `_mollie_payment_id` lookup in
 * MollieObject::getActiveMolliePayment().
 *
 * @covers \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager::displayInstructions
 */
class OrderInstructionsIntegrationTest extends IntegrationMockedTestCase
{
    use APIMockTrait;

    private const GATEWAY_ID = 'mollie_wc_gateway_banktransfer';
    private const TRANSFER_REFERENCE = 'RF49-0000-4716-6216';
    private const BANK_ACCOUNT = 'NL01MOLL0000000001';

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();
        $this->resetAlreadyDisplayedFlags();
        $this->resetPaymentObjectCache();

        $this->removeInstructionHooks();
    }

    public function tearDown(): void
    {
        // The instruction callbacks this test attaches would otherwise stay on the email hooks and
        // render into unrelated tests later in the suite.
        $this->removeInstructionHooks();
        parent::tearDown();
    }

    /**
     * bootstrapModule() re-registers the instruction hooks on every call, so without this a test
     * inherits the callbacks every earlier test left behind — and leaves its own behind in turn.
     */
    private function removeInstructionHooks(): void
    {
        remove_all_actions('woocommerce_after_register_post_type');
        remove_all_actions('woocommerce_email_after_order_table');
        remove_all_actions('woocommerce_email_order_meta');
    }

    /**
     * displayInstructions() renders at most once per request, guarded by two static flags. They
     * survive between tests in the same process, so a second test would silently render nothing.
     */
    private function resetAlreadyDisplayedFlags(): void
    {
        foreach (['alreadyDisplayedAdminInstructions', 'alreadyDisplayedCustomerInstructions'] as $property) {
            $ref = new ReflectionProperty(OrderInstructionsManager::class, $property);
            $ref->setAccessible(true);
            $ref->setValue(null, false);
        }
    }

    /**
     * MollieObject caches fetched payments statically, keyed by payment id.
     */
    private function resetPaymentObjectCache(): void
    {
        $ref = new ReflectionProperty(\Mollie\WooCommerce\Payment\MollieObject::class, 'paymentObjectCache');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    /**
     * A bank transfer order that was cancelled by the expiry webhook and then put back to
     * "Pending payment" by the shop admin so the customer can pay again — the scenario from
     * GH #1289. The Mollie payment id stays on the order, which is why the instructions could
     * come back from the API.
     */
    private function createReinstatedOrder(string $paymentId): WC_Order
    {
        $order = $this->getConfiguredOrder(
            1,
            self::GATEWAY_ID,
            ['simple'],
            [],
            false,
            $paymentId
        );

        $order->update_meta_data('_mollie_payment_id', $paymentId);
        $order->update_meta_data('_mollie_payment_mode', 'test');
        $order->set_status('pending');
        $order->save();

        return $order;
    }

    /**
     * @param string $paymentId
     * @param string $status
     * @param int $orderId
     */
    private function mockBanktransferPayment(string $paymentId, string $status, int $orderId): void
    {
        $this->mockSuccessfulPaymentGet($paymentId, $status, [
            'method' => 'banktransfer',
            'mode' => 'test',
            'metadata' => ['order_id' => $orderId],
            'expiresAt' => '2020-01-01T00:00:00+00:00',
            'details' => (object) [
                'bankName' => 'Stichting Mollie Payments',
                'bankAccount' => self::BANK_ACCOUNT,
                'bankBic' => 'MOLLINL2',
                'transferReference' => self::TRANSFER_REFERENCE,
            ],
        ]);
    }

    /**
     * Boots the plugin against the mocked API and fires the gateway registration hook, which is
     * what attaches the instruction callbacks to the WooCommerce email hooks.
     */
    private function bootPluginWithMockedApi(): void
    {
        $this->bootstrapModule($this->getMockedApiServices());
        do_action('woocommerce_after_register_post_type');
    }

    /**
     * @param WC_Order $order
     * @param bool $sentToAdmin
     * @return string
     */
    private function renderCustomerEmailSection(WC_Order $order, bool $sentToAdmin = false): string
    {
        ob_start();
        do_action('woocommerce_email_after_order_table', $order, $sentToAdmin, false);
        return (string) ob_get_clean();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function statusesThatCanNoLongerBePaidProvider(): array
    {
        return [
            'expired' => ['expired'],
            'canceled' => ['canceled'],
            'failed' => ['failed'],
        ];
    }

    /**
     * Scenario: a reinstated order whose Mollie payment can no longer be paid
     *   Given a bank transfer order that the shop admin set back to "Pending payment"
     *     And the Mollie payment it still points at is expired, canceled or failed
     *   When WooCommerce renders the order details email
     *   Then no bank account, payment reference or expiry date is rendered
     *     And no instructions are stored on the order
     *
     * @test
     * @dataProvider statusesThatCanNoLongerBePaidProvider
     * @group integration
     * @group instructions
     *
     * @param string $status
     */
    public function it_renders_no_instructions_in_the_order_email_when_the_payment_can_no_longer_be_paid(string $status): void
    {
        $paymentId = uniqid('tr_unpayable_', false);
        $order = $this->createReinstatedOrder($paymentId);
        $this->mockBanktransferPayment($paymentId, $status, $order->get_id());
        $this->bootPluginWithMockedApi();

        $output = $this->renderCustomerEmailSection(wc_get_order($order->get_id()));

        self::assertStringNotContainsString(self::TRANSFER_REFERENCE, $output);
        self::assertStringNotContainsString('NL01 MOLL 0000 0000 01', $output);
        self::assertStringNotContainsString('Beneficiary', $output);
        self::assertStringNotContainsString('The payment will expire on', $output);
        self::assertSame('', trim($output));
        self::assertSame(
            '',
            (string) wc_get_order($order->get_id())->get_meta('_mollie_payment_instructions')
        );
    }

    /**
     * Scenario: a reinstated order whose Mollie payment can still be paid
     *   Given a bank transfer order that the shop admin set back to "Pending payment"
     *     And the Mollie payment it points at is still open
     *   When WooCommerce renders the order details email
     *   Then the bank account, payment reference and expiry date are rendered as before
     *
     * @test
     * @group integration
     * @group instructions
     */
    public function it_still_renders_instructions_in_the_order_email_when_the_payment_can_still_be_paid(): void
    {
        $paymentId = uniqid('tr_open_', false);
        $order = $this->createReinstatedOrder($paymentId);
        $this->mockBanktransferPayment($paymentId, 'open', $order->get_id());
        $this->bootPluginWithMockedApi();

        $output = $this->renderCustomerEmailSection(wc_get_order($order->get_id()));

        self::assertStringContainsString(self::TRANSFER_REFERENCE, $output);
        self::assertStringContainsString('NL01 MOLL 0000 0000 01', $output);
        self::assertStringContainsString('Beneficiary', $output);
        self::assertStringContainsString('The payment will expire on', $output);
    }
}
