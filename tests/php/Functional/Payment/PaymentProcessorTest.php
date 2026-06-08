<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mockery;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order as MollieApiOrder;
use Mollie\Api\Resources\Payment as MollieApiPayment;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processPayment
 */
class PaymentProcessorTest extends TestCase
{
    private HelperMocks $helperMocks;
    private MollieApiClient $apiClientMock;
    /** @var Mockery\MockInterface */
    private $orderEndpointMock;
    /** @var Mockery\MockInterface */
    private $paymentEndpointMock;
    /** @var Mockery\MockInterface&LoggerInterface */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helperMocks = new HelperMocks();

        $this->orderEndpointMock = Mockery::mock(OrderEndpoint::class);
        $this->paymentEndpointMock = Mockery::mock(PaymentEndpoint::class);

        $this->apiClientMock = $this->createMock(MollieApiClient::class);
        $this->apiClientMock->orders = $this->orderEndpointMock;
        $this->apiClientMock->payments = $this->paymentEndpointMock;

        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('debug')->withAnyArgs()->andReturnNull()->byDefault();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function buildSut(): PaymentProcessor
    {
        return new PaymentProcessor(
            $this->helperMocks->noticeMock(),
            $this->logger,
            $this->helperMocks->paymentFactory(),
            $this->helperMocks->dataHelper($this->apiClientMock),
            $this->helperMocks->apiHelper($this->apiClientMock),
            $this->helperMocks->settingsHelper(),
            $this->helperMocks->pluginId(),
            $this->createMock(PaymentCheckoutRedirectService::class),
            []
        );
    }

    private function invokeCancel(PaymentProcessor $sut, object $order, string $apiKey = 'test_key'): ?array
    {
        $method = new ReflectionMethod(PaymentProcessor::class, 'cancelExistingMolliePaymentIfPending');
        $method->setAccessible(true);
        return $method->invoke($sut, $order, $apiKey);
    }

    private function wcOrderWithMeta(array $metaMap = []): object
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')
            ->withAnyArgs()
            ->andReturnUsing(static function (string $key) use ($metaMap): string {
                return $metaMap[$key] ?? '';
            })
            ->byDefault();
        $order->shouldReceive('add_order_note')->withAnyArgs()->andReturnNull()->byDefault();
        return $order;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 1 — ord_ meta, cancellable → cancel endpoint + order note
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When processPayment() is called for an order whose _mollie_order_id
     *           meta is set to an 'ord_*' value and the corresponding Mollie order is
     *           in a cancellable state (isCreated || isAuthorized || isShipping) and
     *           not yet canceled, cancelExistingMolliePaymentIfPending() calls the
     *           Mollie orders cancel endpoint before processPaymentForMollie() creates
     *           a new payment, and adds an order note recording the cancelled payment id.
     */
    public function test_cancels_mollie_order_when_ord_meta_present_and_cancellable(): void
    {
        // Arrange
        $mollieOrderId = 'ord_abc123';

        $mollieOrder = Mockery::mock(MollieApiOrder::class);
        $mollieOrder->shouldReceive('isCanceled')->andReturn(false);
        $mollieOrder->shouldReceive('isCreated')->andReturn(true);
        $mollieOrder->shouldReceive('cancel')->once();

        $this->orderEndpointMock->shouldReceive('get')->andReturn($mollieOrder);

        $order = $this->wcOrderWithMeta(['_mollie_order_id' => $mollieOrderId]);
        $order->shouldReceive('add_order_note')
            ->once()
            ->withArgs(static function (string $note) use ($mollieOrderId): bool {
                return strpos($note, $mollieOrderId) !== false;
            });

        // When
        $this->invokeCancel($this->buildSut(), $order);

        // Then — Mockery verifies cancel() once and add_order_note() once in tearDown
        $this->assertTrue(true);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 2 — tr_ meta, isCancelable=true → payments cancel + note
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When processPayment() is called for an order whose _mollie_payment_id
     *           meta is set to a 'tr_*' value and the corresponding Mollie payment has
     *           isCancelable=true and is not canceled/paid/authorized,
     *           cancelExistingMolliePaymentIfPending() calls the Mollie payments cancel
     *           endpoint before processPaymentForMollie() creates a new payment, and
     *           adds an order note recording the cancelled payment id.
     */
    public function test_cancels_mollie_payment_when_tr_meta_present_and_cancelable(): void
    {
        // Arrange
        $paymentId = 'tr_xyz789';

        $molliePayment = Mockery::mock(MollieApiPayment::class);
        $molliePayment->isCancelable = true;
        $molliePayment->shouldReceive('isCanceled')->andReturn(false);
        $molliePayment->shouldReceive('isPaid')->andReturn(false);
        $molliePayment->shouldReceive('isAuthorized')->andReturn(false);

        $this->paymentEndpointMock->shouldReceive('get')->andReturn($molliePayment);
        $this->paymentEndpointMock->shouldReceive('cancel')->once();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'  => '',
            '_mollie_payment_id' => $paymentId,
        ]);
        $order->shouldReceive('add_order_note')
            ->once()
            ->withArgs(static function (string $note) use ($paymentId): bool {
                return strpos($note, $paymentId) !== false;
            });

        // When
        $this->invokeCancel($this->buildSut(), $order);

        // Then — Mockery verifies cancel() once and add_order_note() once in tearDown
        $this->assertTrue(true);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 3 — terminal state → cancel NOT called, debug logged
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When the previous Mollie payment/order is already in a terminal state
     *           (paid, authorized, canceled, expired, failed),
     *           cancelExistingMolliePaymentIfPending() does NOT call the cancel endpoint
     *           and proceeds to new-payment creation; the previous-payment status is
     *           logged at debug level.
     */
    public function test_skips_cancel_when_previous_payment_in_terminal_state(): void
    {
        // Arrange
        $mollieOrderId = 'ord_terminal';

        $mollieOrder = Mockery::mock(MollieApiOrder::class);
        $mollieOrder->shouldReceive('isCanceled')->andReturn(true);
        $mollieOrder->shouldReceive('cancel')->never();

        $this->orderEndpointMock->shouldReceive('get')->andReturn($mollieOrder);

        $order = $this->wcOrderWithMeta(['_mollie_order_id' => $mollieOrderId]);
        $order->shouldReceive('add_order_note')->never();

        $this->logger->shouldReceive('debug')->atLeast()->once()->withAnyArgs();

        // When
        $this->invokeCancel($this->buildSut(), $order);

        // Then — Mockery verifies cancel() never, add_order_note() never, debug logged
        $this->assertTrue(true);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 4 — ApiException → logged at debug, does not propagate
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When the Mollie API throws ApiException during lookup or cancel
     *           (network error, payment-not-found, not-cancellable error code),
     *           cancelExistingMolliePaymentIfPending() logs the exception at debug
     *           level and returns without throwing; processPayment() continues to
     *           create the new payment.
     */
    public function test_logs_and_does_not_throw_when_api_exception_during_cancel(): void
    {
        // Arrange
        $mollieOrderId = 'ord_fail';
        $exception = new ApiException('Network error');

        $this->orderEndpointMock->shouldReceive('get')->andThrow($exception);

        $order = $this->wcOrderWithMeta(['_mollie_order_id' => $mollieOrderId]);
        $order->shouldReceive('add_order_note')->never();

        $this->logger->shouldReceive('debug')
            ->once()
            ->withArgs(static function (string $message) use ($exception): bool {
                return strpos($message, $exception->getMessage()) !== false;
            });

        // When — must not propagate the exception
        $this->invokeCancel($this->buildSut(), $order);

        // Then — no exception + debug logged with exception message
        $this->assertTrue(true);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 5 — no meta → no API call, no order note
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When processPayment() is called for an order with no _mollie_order_id
     *           and no _mollie_payment_id meta, cancelExistingMolliePaymentIfPending()
     *           is a no-op (no API call, no order note) and new-payment creation
     *           proceeds normally.
     */
    public function test_no_api_call_when_order_has_no_mollie_meta(): void
    {
        // Arrange
        $this->orderEndpointMock->shouldReceive('get')->never();
        $this->paymentEndpointMock->shouldReceive('get')->never();
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'   => '',
            '_mollie_payment_id' => '',
        ]);
        $order->shouldReceive('add_order_note')->never();

        // When
        $this->invokeCancel($this->buildSut(), $order);

        // Then — Mockery verifies never() expectations in tearDown
        $this->assertTrue(true);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 6 — is_paid()=true → immediate ['result' => 'failure']
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When processPayment() is called and $order->is_paid() returns true
     *           at entry, the method returns ['result' => 'failure'] immediately
     *           without calling cancelExistingMolliePaymentIfPending(),
     *           processPaymentForMollie(), or any Mollie API.
     */
    public function test_returns_failure_immediately_when_order_is_already_paid(): void
    {
        // Arrange — use a partial mock so we can assert processPaymentForMollie() is never called.
        // Before phase 03: no is_paid() guard → code reaches processPaymentForMollie() → never() fails.
        // After phase 03: is_paid() guard returns early → never() passes.
        $gatewayId = 'mollie_wc_gateway_ideal';
        $deprecatedGatewayHelper = $this->helperMocks->mollieGatewayBuilder('Ideal', false, false, []);

        $sut = $this->getMockBuilder(PaymentProcessor::class)
            ->setConstructorArgs([
                $this->helperMocks->noticeMock(),
                $this->logger,
                $this->helperMocks->paymentFactory(),
                $this->helperMocks->dataHelper($this->apiClientMock),
                $this->helperMocks->apiHelper($this->apiClientMock),
                $this->helperMocks->settingsHelper(),
                $this->helperMocks->pluginId(),
                $this->createMock(PaymentCheckoutRedirectService::class),
                [$gatewayId => $deprecatedGatewayHelper],
            ])
            ->onlyMethods([
                'processPaymentForMollie',
                'processInitialOrderStatus',
                'getUserMollieCustomerId',
                'needsSubscriptionSwitch',
                'paymentTypeBasedOnGateway',
                'paymentTypeBasedOnProducts',
            ])
            ->getMock();

        $sut->expects($this->never())->method('processPaymentForMollie');
        $sut->method('processInitialOrderStatus')->willReturn('pending');
        $sut->method('getUserMollieCustomerId')->willReturn(null);
        $sut->method('needsSubscriptionSwitch')->willReturn(false);
        $sut->method('paymentTypeBasedOnGateway')->willReturn(PaymentProcessor::PAYMENT_METHOD_TYPE_ORDER);
        $sut->method('paymentTypeBasedOnProducts')->willReturn(PaymentProcessor::PAYMENT_METHOD_TYPE_ORDER);

        $gateway = $this->helperMocks->genericPaymentGatewayMock();
        $gateway->id = $gatewayId;
        $gateway->method('get_return_url')->willReturn('https://example.com/return');

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(42);
        $order->shouldReceive('is_paid')->andReturn(true);

        // When
        $result = $sut->processPayment($order, $gateway);

        // Then
        $this->assertSame(['result' => 'failure'], $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 7 — subscription switch path → cancel logic NOT reached
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When processPayment() is entered through the needsSubscriptionSwitch()
     *           path (subscription switch with 0.00 total), cancelExistingMolliePaymentIfPending()
     *           is NOT invoked — the cancel-before-create logic must be placed after
     *           the subscription-switch early-return.
     */
    public function test_subscription_switch_path_does_not_invoke_cancel_logic(): void
    {
        // Arrange — cancel endpoints must never be reached when subscription switch exits early
        $this->orderEndpointMock->shouldReceive('get')->never();
        $this->paymentEndpointMock->shouldReceive('get')->never();
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $gatewayId = 'mollie_wc_gateway_ideal';
        $deprecatedGatewayHelper = $this->helperMocks->mollieGatewayBuilder('Ideal', false, false, []);

        $sut = $this->getMockBuilder(PaymentProcessor::class)
            ->setConstructorArgs([
                $this->helperMocks->noticeMock(),
                $this->logger,
                $this->helperMocks->paymentFactory(),
                $this->helperMocks->dataHelper($this->apiClientMock),
                $this->helperMocks->apiHelper($this->apiClientMock),
                $this->helperMocks->settingsHelper(),
                $this->helperMocks->pluginId(),
                $this->createMock(PaymentCheckoutRedirectService::class),
                [$gatewayId => $deprecatedGatewayHelper],
            ])
            ->onlyMethods([
                'needsSubscriptionSwitch',
                'processSubscriptionSwitch',
                'processInitialOrderStatus',
                'getUserMollieCustomerId',
            ])
            ->getMock();

        $sut->method('needsSubscriptionSwitch')->willReturn(true);
        $sut->method('processSubscriptionSwitch')->willReturn(['result' => 'failure']);
        $sut->method('processInitialOrderStatus')->willReturn('pending');
        $sut->method('getUserMollieCustomerId')->willReturn(null);

        $gateway = $this->helperMocks->genericPaymentGatewayMock();
        $gateway->id = $gatewayId;
        $gateway->method('get_return_url')->willReturn('https://example.com/return');

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(99);
        $order->shouldReceive('is_paid')->andReturn(false);

        // When
        $result = $sut->processPayment($order, $gateway);

        // Then
        $this->assertSame(['result' => 'failure'], $result);
        // Mockery verifies never() on endpoints in tearDown
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 8 — non-cancellable active payment WITH checkout URL → redirect
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When the existing tr_ payment is non-cancellable, not terminal, not
     *           authorized, and Mollie returns a checkoutUrl, processPayment() must
     *           redirect the customer to that URL instead of creating a new payment.
     */
    public function test_redirects_to_existing_payment_when_non_cancellable_with_checkout_url(): void
    {
        $paymentId = 'tr_active1';
        $checkoutUrl = 'https://checkout.mollie.com/pay/tr_active1';

        $molliePayment = Mockery::mock(MollieApiPayment::class);
        $molliePayment->isCancelable = false;
        $molliePayment->shouldReceive('isCanceled')->andReturn(false);
        $molliePayment->shouldReceive('isPaid')->andReturn(false);
        $molliePayment->shouldReceive('isExpired')->andReturn(false);
        $molliePayment->shouldReceive('isFailed')->andReturn(false);
        $molliePayment->shouldReceive('isAuthorized')->andReturn(false);
        $molliePayment->shouldReceive('getCheckoutUrl')->andReturn($checkoutUrl);

        $this->paymentEndpointMock->shouldReceive('get')->andReturn($molliePayment);
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'   => '',
            '_mollie_payment_id' => $paymentId,
        ]);
        $order->shouldReceive('add_order_note')->never();

        $result = $this->invokeCancel($this->buildSut(), $order);

        $this->assertSame(['result' => 'success', 'redirect' => $checkoutUrl], $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 9 — non-cancellable active payment WITHOUT checkout URL → block
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When the existing tr_ payment is non-cancellable, not terminal, not
     *           authorized, and no checkoutUrl is available (e.g. iDEAL pending at
     *           bank), processPayment() must show an error notice and return failure.
     */
    public function test_blocks_with_error_notice_when_non_cancellable_no_checkout_url(): void
    {
        $paymentId = 'tr_pending1';

        $molliePayment = Mockery::mock(MollieApiPayment::class);
        $molliePayment->isCancelable = false;
        $molliePayment->shouldReceive('isCanceled')->andReturn(false);
        $molliePayment->shouldReceive('isPaid')->andReturn(false);
        $molliePayment->shouldReceive('isExpired')->andReturn(false);
        $molliePayment->shouldReceive('isFailed')->andReturn(false);
        $molliePayment->shouldReceive('isAuthorized')->andReturn(false);
        $molliePayment->shouldReceive('getCheckoutUrl')->andReturn(null);

        $this->paymentEndpointMock->shouldReceive('get')->andReturn($molliePayment);
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'   => '',
            '_mollie_payment_id' => $paymentId,
        ]);
        $order->shouldReceive('add_order_note')->never();

        $noticeMock = Mockery::mock(\Mollie\WooCommerce\Notice\NoticeInterface::class);
        $noticeMock->shouldReceive('addNotice')
            ->once()
            ->with('error', Mockery::type('string'));

        $sut = new PaymentProcessor(
            $noticeMock,
            $this->logger,
            $this->helperMocks->paymentFactory(),
            $this->helperMocks->dataHelper($this->apiClientMock),
            $this->helperMocks->apiHelper($this->apiClientMock),
            $this->helperMocks->settingsHelper(),
            $this->helperMocks->pluginId(),
            $this->createMock(PaymentCheckoutRedirectService::class),
            []
        );

        $result = $this->invokeCancel($sut, $order);

        $this->assertSame(['result' => 'failure'], $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 10 — tr_ payment in terminal state → null (proceed)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When the existing tr_ payment is in a terminal state (paid, canceled,
     *           expired, or failed), cancelExistingMolliePaymentIfPending() returns null
     *           so processPayment() proceeds to create a new payment normally.
     */
    public function test_proceeds_when_tr_payment_in_terminal_state(): void
    {
        $paymentId = 'tr_paid1';

        $molliePayment = Mockery::mock(MollieApiPayment::class);
        $molliePayment->isCancelable = false;
        $molliePayment->shouldReceive('isCanceled')->andReturn(false);
        $molliePayment->shouldReceive('isPaid')->andReturn(true);

        $this->paymentEndpointMock->shouldReceive('get')->andReturn($molliePayment);
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'   => '',
            '_mollie_payment_id' => $paymentId,
        ]);
        $order->shouldReceive('add_order_note')->never();

        $result = $this->invokeCancel($this->buildSut(), $order);

        $this->assertNull($result);
    }
}
