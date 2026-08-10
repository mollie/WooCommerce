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
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentFactory;
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
        $order->shouldReceive('get_id')->andReturn(1)->byDefault();
        $order->shouldReceive('get_meta')
            ->withAnyArgs()
            ->andReturnUsing(static function (string $key) use ($metaMap): string {
                return $metaMap[$key] ?? '';
            })
            ->byDefault();
        $order->shouldReceive('add_order_note')->withAnyArgs()->andReturnNull()->byDefault();
        return $order;
    }

    private function gatewayWithId(string $gatewayId): object
    {
        $gateway = $this->helperMocks->genericPaymentGatewayMock();
        $gateway->id = $gatewayId;
        return $gateway;
    }

    private function buildSutWithGateway(string $gatewayId): PaymentProcessor
    {
        $sut = $this->buildSut();
        $sut->setGateway($this->gatewayWithId($gatewayId));
        return $sut;
    }

    /**
     * A non-cancellable, still-open tr_ payment: not terminal, not authorized, with a checkout URL.
     */
    private function nonCancellableOpenPayment(string $checkoutUrl): object
    {
        $molliePayment = Mockery::mock(MollieApiPayment::class);
        $molliePayment->isCancelable = false;
        $molliePayment->shouldReceive('isCanceled')->andReturn(false);
        $molliePayment->shouldReceive('isPaid')->andReturn(false);
        $molliePayment->shouldReceive('isExpired')->andReturn(false);
        $molliePayment->shouldReceive('isFailed')->andReturn(false);
        $molliePayment->shouldReceive('isAuthorized')->andReturn(false);
        $molliePayment->shouldReceive('getCheckoutUrl')->andReturn($checkoutUrl);
        return $molliePayment;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 1 — ord_ meta, cancellable → cancel endpoint + order note
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario A cancellable Mollie order is cancelled before a new payment
     *   Given an order whose _mollie_order_id points to a cancellable, not-yet-cancelled Mollie order
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it calls the orders cancel endpoint and adds an order note with the cancelled id
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
     * @scenario A cancellable Mollie payment is cancelled before a new payment
     *   Given an order whose _mollie_payment_id points to a cancelable, not canceled/paid/authorized payment
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it calls the payments cancel endpoint and adds an order note with the cancelled id
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
     * @scenario A terminal previous payment is not cancelled
     *   Given a previous Mollie payment/order already in a terminal state (paid, authorized, canceled, expired, failed)
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it does not call the cancel endpoint, logs the status at debug, and proceeds to create a new payment
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
     * @scenario An API exception during cancel is swallowed
     *   Given the Mollie API throws ApiException during lookup or cancel
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it logs the exception at debug and returns without throwing, so processPayment() continues
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
     * @scenario No Mollie meta means no cancel work
     *   Given an order with no _mollie_order_id and no _mollie_payment_id meta
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it makes no API call, adds no order note, and new-payment creation proceeds normally
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
     * @scenario An already-paid order is rejected immediately
     *   Given an order whose is_paid() returns true at entry
     *   When processPayment() is called
     *   Then it returns ['result' => 'failure'] at once, never touching the cancel logic, processPaymentForMollie(), or any Mollie API
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
     * @scenario The subscription-switch path skips the cancel logic
     *   Given processPayment() is entered through the needsSubscriptionSwitch() early-return
     *   When the payment is processed
     *   Then cancelExistingMolliePaymentIfPending() is never invoked
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
     * @scenario A non-cancellable payment with a checkout URL redirects
     *   Given an open, non-cancellable, non-terminal tr_ payment for which Mollie returns a checkout URL
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it redirects the customer to that URL instead of creating a new payment
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
     * @scenario A non-cancellable payment with no checkout URL is blocked
     *   Given an open, non-cancellable, non-terminal tr_ payment with no checkout URL (e.g. iDEAL pending at bank)
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it shows an error notice and returns failure
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
     * @scenario A terminal tr_ payment lets a new payment proceed
     *   Given an existing tr_ payment in a terminal state (paid, canceled, expired, or failed)
     *   When cancelExistingMolliePaymentIfPending() runs
     *   Then it returns null so processPayment() proceeds to create a new payment normally
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

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 1 (method switch) — different gateway than the open payment → create new
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario Switching to a different method creates a new payment
     *   Given an order with an open, non-cancellable payment created by another gateway
     *   When the customer starts checkout with a different payment method
     *   Then cancelExistingMolliePaymentIfPending() returns null so a new payment is created
     */
    public function test_creates_new_payment_when_selected_gateway_differs_from_non_cancellable_payment(): void
    {
        // Arrange
        $paymentId = 'tr_active_switch';
        $creatingGateway = 'mollie_wc_gateway_paybybank';
        $selectedGateway = 'mollie_wc_gateway_klarnapaylater';

        $this->paymentEndpointMock->shouldReceive('get')
            ->andReturn($this->nonCancellableOpenPayment('https://checkout.instantbankpayment.com/?session=x'));
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'       => '',
            '_mollie_payment_id'     => $paymentId,
            '_mollie_payment_method' => $creatingGateway,
        ]);
        $order->shouldReceive('add_order_note')->never();

        // When — customer picked a different method than the one that created the open payment
        $result = $this->invokeCancel($this->buildSutWithGateway($selectedGateway), $order);

        // Then — proceed to create a new payment for the newly selected method
        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 2 (method switch) — same gateway → reuse existing checkout URL
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario Re-selecting the same method reuses the open payment
     *   Given an order with an open, non-cancellable payment created by a gateway
     *   When the customer starts checkout again with that same gateway
     *   Then it redirects to the existing checkout URL and creates no new payment
     */
    public function test_reuses_existing_payment_when_selected_gateway_matches_non_cancellable_payment(): void
    {
        // Arrange
        $paymentId = 'tr_active_same';
        $checkoutUrl = 'https://checkout.instantbankpayment.com/?session=y';
        $gateway = 'mollie_wc_gateway_paybybank';

        $this->paymentEndpointMock->shouldReceive('get')
            ->andReturn($this->nonCancellableOpenPayment($checkoutUrl));
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'       => '',
            '_mollie_payment_id'     => $paymentId,
            '_mollie_payment_method' => $gateway,
        ]);
        $order->shouldReceive('add_order_note')->never();

        // When — customer re-selected the same method that created the open payment
        $result = $this->invokeCancel($this->buildSutWithGateway($gateway), $order);

        // Then — reuse the existing payment (no new payment created)
        $this->assertSame(['result' => 'success', 'redirect' => $checkoutUrl], $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 1 boundary — creating-gateway meta empty (legacy/in-flight) → do not duplicate
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario Unknown creating gateway falls back to redirect, never a duplicate
     *   Given an order with an open, non-cancellable payment and no recorded creating gateway
     *   When the customer starts checkout with a different payment method
     *   Then it falls back to the existing redirect instead of creating a duplicate payment
     */
    public function test_redirects_when_creating_gateway_meta_is_empty_for_non_cancellable_payment(): void
    {
        // Arrange
        $paymentId = 'tr_active_legacy';
        $checkoutUrl = 'https://checkout.instantbankpayment.com/?session=z';

        $this->paymentEndpointMock->shouldReceive('get')
            ->andReturn($this->nonCancellableOpenPayment($checkoutUrl));
        $this->paymentEndpointMock->shouldReceive('cancel')->never();

        $order = $this->wcOrderWithMeta([
            '_mollie_order_id'       => '',
            '_mollie_payment_id'     => $paymentId,
            '_mollie_payment_method' => '',
        ]);
        $order->shouldReceive('add_order_note')->never();

        // When — selected gateway differs, but the creating gateway is unknown
        $result = $this->invokeCancel(
            $this->buildSutWithGateway('mollie_wc_gateway_klarnapaylater'),
            $order
        );

        // Then — fall back to redirect rather than creating an unguarded duplicate payment
        $this->assertSame(['result' => 'success', 'redirect' => $checkoutUrl], $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Criterion 3 — persist the creating gateway to order meta when saving Mollie info
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario Saving Mollie info records the creating gateway
     *   Given a payment just created for an order via a specific processing gateway
     *   When saveMollieInfo() runs
     *   Then _mollie_payment_method is set to that gateway id, not the mutable _payment_method
     */
    public function test_persists_creating_gateway_to_order_meta_when_saving_mollie_info(): void
    {
        // Arrange
        $selectedGateway = 'mollie_wc_gateway_ideal';
        $molliePaymentId = 'tr_new_paid';

        $paymentObject = Mockery::mock(MolliePayment::class);
        $paymentObject->shouldReceive('setPayment')->andReturnNull();
        $paymentObject->shouldReceive('setActiveMolliePayment')->andReturnNull();
        $paymentObject->shouldReceive('data')->andReturn((object)['id' => $molliePaymentId]);
        // Empty customer id keeps saveMollieInfo out of the WC_Customer path in the functional bootstrap.
        $paymentObject->shouldReceive('getMollieCustomerIdFromPaymentObject')->andReturn('');

        $paymentFactory = new PaymentFactory(
            static function () {
                return Mockery::mock(MollieOrder::class);
            },
            static function () use ($paymentObject) {
                return $paymentObject;
            }
        );

        $sut = new PaymentProcessor(
            $this->helperMocks->noticeMock(),
            $this->logger,
            $paymentFactory,
            $this->helperMocks->dataHelper($this->apiClientMock),
            $this->helperMocks->apiHelper($this->apiClientMock),
            $this->helperMocks->settingsHelper(),
            $this->helperMocks->pluginId(),
            $this->createMock(PaymentCheckoutRedirectService::class),
            []
        );
        $sut->setGateway($this->gatewayWithId($selectedGateway));

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(4242);
        $order->shouldReceive('get_customer_id')->andReturn(1);
        // get_meta returns empty for everything: if the impl wrongly read _payment_method it
        // would persist '' — so the asserted non-empty gateway id can only come from $this->gateway.
        $order->shouldReceive('get_meta')->withAnyArgs()->andReturn('')->byDefault();
        $order->shouldReceive('save')->andReturnNull()->byDefault();
        $order->shouldReceive('update_meta_data')
            ->once()
            ->with('_mollie_payment_method', $selectedGateway);

        // When
        $method = new ReflectionMethod(PaymentProcessor::class, 'saveMollieInfo');
        $method->setAccessible(true);
        $method->invoke($sut, $order, $molliePaymentId);

        // Then — Mockery verifies update_meta_data('_mollie_payment_method', <gateway>) once
        $this->assertTrue(true);
    }
}
