<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\PaymentMethods\InstructionStrategies;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Resources\Payment as MollieApiPayment;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerceTests\TestCase;
use ReflectionProperty;
use WC_Order;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager::displayInstructions
 */
class OrderInstructionsManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAlreadyDisplayedFlags();
        when('wptexturize')->returnArg(1);
        when('wp_kses')->returnArg(1);
        when('wpautop')->returnArg(1);
        when('wc_date_format')->justReturn('Y-m-d');
        when('esc_html')->returnArg(1);
        when('date_i18n')->alias(static function ($format, $timestamp) {
            return date($format, $timestamp);
        });
    }

    private function resetAlreadyDisplayedFlags(): void
    {
        foreach (['alreadyDisplayedAdminInstructions', 'alreadyDisplayedCustomerInstructions'] as $property) {
            $ref = new ReflectionProperty(OrderInstructionsManager::class, $property);
            $ref->setAccessible(true);
            $ref->setValue(null, false);
        }
    }

    /**
     * Reinstated order scenario from GH #1289: a bank-transfer order that was cancelled by the
     * expiry webhook, then set back to "Pending payment" by an admin so the customer can retry.
     */
    private function makeReinstatedOrder(): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_banktransfer');
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('has_status')->with('on-hold')->andReturn(false);
        $order->shouldReceive('has_status')->with('pending')->andReturn(true);
        return $order;
    }

    private function makeBanktransferPayment(array $statuses): MollieApiPayment
    {
        $payment = Mockery::mock(MollieApiPayment::class);
        $payment->method = 'banktransfer';
        $payment->expiresAt = '2020-01-01T00:00:00+00:00';
        $payment->details = (object) [
            'bankName' => 'Stichting Mollie Payments',
            'bankAccount' => 'NL01MOLL0000000001',
            'bankBic' => 'MOLLINL2',
            'transferReference' => 'RF49-0000-4716-6216',
        ];
        $payment->shouldReceive('isExpired')->andReturn($statuses['expired'] ?? false);
        $payment->shouldReceive('isCanceled')->andReturn($statuses['canceled'] ?? false);
        $payment->shouldReceive('isFailed')->andReturn($statuses['failed'] ?? false);
        $payment->shouldReceive('isPaid')->andReturn($statuses['paid'] ?? false);
        return $payment;
    }

    private function makeGatewayHelper(MollieApiPayment $payment): object
    {
        $mollieObject = Mockery::mock();
        $mollieObject->shouldReceive('getActiveMolliePayment')->andReturn($payment);

        $helper = Mockery::mock();
        $helper->shouldReceive('paymentObject')->andReturn($mollieObject);

        $paymentMethod = Mockery::mock();
        $paymentMethod->shouldReceive('getProperty')->with('instructions')->andReturn(true);
        $paymentMethod->shouldReceive('getProperty')->with('id')->andReturn('banktransfer');
        $helper->shouldReceive('paymentMethod')->andReturn($paymentMethod);

        return $helper;
    }

    public function statusesThatCanNoLongerBePaidProvider(): array
    {
        return [
            'expired' => [['expired' => true]],
            'canceled' => [['canceled' => true]],
            'failed' => [['failed' => true]],
        ];
    }

    /**
     * @dataProvider statusesThatCanNoLongerBePaidProvider
     * @scenario GH #1289: a merchant reinstates a cancelled order to "Pending payment" so the
     *           customer can pay again, then sends the order details email. If the underlying
     *           Mollie payment can no longer be paid (expired, canceled, or failed), its stale
     *           IBAN/reference/expiry-date must not be rendered — the customer would transfer
     *           money against a reference Mollie has already refunded.
     */
    public function test_no_instructions_rendered_for_a_reinstated_order_whose_payment_can_no_longer_be_paid(array $statuses): void
    {
        // Arrange
        $paymentGateway = (object) ['id' => 'mollie_wc_gateway_banktransfer'];
        $payment = $this->makeBanktransferPayment($statuses);
        $helper = $this->makeGatewayHelper($payment);
        $order = $this->makeReinstatedOrder();
        $order->shouldNotReceive('update_meta_data');
        $order->shouldNotReceive('save');

        $sut = new OrderInstructionsManager();

        // When
        ob_start();
        $sut->displayInstructions($paymentGateway, $helper, $order, false, false);
        $output = ob_get_clean();

        // Then
        self::assertSame('', $output);
    }

    /** @scenario A reinstated order whose payment can still be paid keeps rendering the bank transfer instructions as before. */
    public function test_instructions_still_rendered_for_a_reinstated_order_whose_payment_can_still_be_paid(): void
    {
        // Arrange
        $paymentGateway = (object) ['id' => 'mollie_wc_gateway_banktransfer'];
        $payment = $this->makeBanktransferPayment([]);
        $helper = $this->makeGatewayHelper($payment);
        $order = $this->makeReinstatedOrder();
        $order->shouldReceive('update_meta_data')->once();
        $order->shouldReceive('save')->once();

        $sut = new OrderInstructionsManager();

        // When
        ob_start();
        $sut->displayInstructions($paymentGateway, $helper, $order, false, true);
        $output = ob_get_clean();

        // Then
        self::assertStringContainsString('RF49-0000-4716-6216', $output);
    }
}
