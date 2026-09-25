<?php

namespace Mollie\WooCommerceTests\Integration\Common;

use Mockery;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\WooCommerceTests\Integration\Common\Traits\IsolatesSiteState;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use WC_Order;
use WC_Payment_Gateways;

/**
 * Shared harness for the tests that drive the customer-facing payment flow end to end: starting a
 * payment (PaymentProcessor::processPayment) and coming back from Mollie
 * (MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder).
 *
 * Everything here is about getting the real entry points to run against a mocked Mollie API and
 * making what they did observable: which endpoint was called, with which payload, and what was left
 * on the WooCommerce order. The scenarios themselves live in the subclasses.
 */
abstract class PaymentFlowTestCase extends IntegrationMockedTestCase
{
    /**
     * The Mollie customer id every test resolves to, pinned on the WordPress user so
     * `payment.customerId` in the request is a known value rather than whatever an earlier run left
     * on the box.
     */
    protected const CUSTOMER_ID = 'cst_integrationtest';

    use APIMockTrait;
    use IsolatesSiteState;

    /**
     * The request payloads handed to orders->create(), in call order.
     *
     * @var array<int, array>
     */
    protected array $ordersCreateCalls = [];

    /**
     * The request payloads handed to payments->create(), in call order.
     *
     * @var array<int, array>
     */
    protected array $paymentsCreateCalls = [];

    /**
     * Previous value of the user's mollie_customer_id meta, restored in tearDown().
     *
     * @var string
     */
    private string $customerIdMetaBackup = '';

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();

        $this->ordersCreateCalls = [];
        $this->paymentsCreateCalls = [];

        // Order notes are an observable side effect of payment creation and are written through
        // __() against the site locale, which is not English on every dev/CI box. Pin the locale so
        // the note assertions in the subclasses compare against the msgids the source declares.
        $this->pinEnglishOrderNotes();

        // Gateway registration reads Data::getAllAvailablePaymentMethods(), which caches the Mollie
        // methods list in a one-hour transient plus a process-wide static. A stale entry from an
        // earlier run silently overrides MockedApi::defaultAvailableMethods(), so a gateway a test
        // needs never registers and processPayment() bails at its gateway-helper lookup.
        $this->flushMollieMethodsCache();

        $this->mockCustomerEndpoints();
        $this->pinMollieCustomerId();
    }

    public function tearDown(): void
    {
        // Fixture orders are removed by IntegrationMockedTestCase::tearDown(). What is restored here
        // is the state this harness touches: the user meta it pinned, and the site state of IsolatesSiteState.
        if ($this->customerIdMetaBackup === '') {
            delete_user_meta($this->customer_id, 'mollie_customer_id');
        } else {
            update_user_meta($this->customer_id, 'mollie_customer_id', $this->customerIdMetaBackup);
        }

        // Options, the methods list a test may have widened, and the locale.
        $this->restoreSiteState();

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Driving the processor
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Boots the plugin against the mocked Mollie API and runs the real checkout entry point.
     *
     * The gateway is taken from WooCommerce's own registry rather than constructed here: the
     * processor reads its id to find the matching gateway helper in the container, and asks it for
     * the return URL, so a hand-rolled double would only prove the double works.
     *
     * The registry is re-initialised on the way in. WC_Payment_Gateways is a lazy singleton that
     * applies the 'woocommerce_payment_gateways' filter once, on first use, and then freezes: if
     * anything resolved it before bootstrapModule() registered the plugin's hooks — an order save
     * firing a status transition is enough — no Mollie gateway is in it, and every test in this
     * hierarchy fails on gateway lookup depending on nothing but call order.
     */
    protected function processPayment(WC_Order $order, string $gatewayId): array
    {
        $container = $this->bootstrapModule($this->getMockedApiServices());
        WC_Payment_Gateways::instance()->init();

        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->assertArrayHasKey($gatewayId, $gateways, "Gateway {$gatewayId} is not registered; the test cannot drive it.");

        $processor = $container->get(PaymentProcessor::class);

        return $processor->processPayment($order, $gateways[$gatewayId]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Mollie API doubles
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Records every orders->create() payload and lets the test decide, per attempt, whether Mollie
     * accepts it. The recording is what makes "the Orders API was not used" and "the retry dropped
     * the customer id" assertable; a plain shouldReceive() would only prove the call arity.
     *
     * @param callable $handler fn(array $requestData, int $attempt): object
     */
    protected function recordOrdersCreate(callable $handler): void
    {
        $this->apiMock()->getMockedApiClient()->orders
            ->shouldReceive('create')
            ->andReturnUsing(function (array $data) use ($handler): object {
                $this->ordersCreateCalls[] = $data;
                return $handler($data, count($this->ordersCreateCalls));
            });
    }

    /**
     * The payments->create() counterpart of recordOrdersCreate(). Every test installs both,
     * including the tests that expect no call at all: a recorded call that should not have happened
     * fails with "expected 0 calls, got 1" instead of an unexplained Mockery BadMethodCallException.
     *
     * @param callable $handler fn(array $requestData, int $attempt): object
     */
    protected function recordPaymentsCreate(callable $handler): void
    {
        $this->apiMock()->getMockedApiClient()->payments
            ->shouldReceive('create')
            ->andReturnUsing(function (array $data) use ($handler): object {
                $this->paymentsCreateCalls[] = $data;
                return $handler($data, count($this->paymentsCreateCalls));
            });
    }

    /**
     * Installs recorders for both create endpoints that fail the test if they are reached. For the
     * scenarios whose whole point is that no payment is created at all.
     */
    protected function recordNoCreateExpected(): void
    {
        $this->recordOrdersCreate(function (): object {
            return $this->createdMollieOrder('ord_unexpected', 'ideal');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_unexpected', 'ideal');
        });
    }

    /**
     * A Mollie order resource as it comes back from orders->create().
     *
     * Also stubs the orders->get() that follows: PaymentProcessor::saveMollieInfo() reads the
     * embedded payment back off the created order to learn the 'tr_' id and the customer behind it.
     */
    protected function createdMollieOrder(string $mollieOrderId, string $method): object
    {
        $this->mockSuccessfulOrderGet($mollieOrderId, 'created', [
            'method' => $method,
            'mode' => 'test',
            '_embedded' => (object) [
                'payments' => [
                    (object) ['id' => 'tr_' . substr($mollieOrderId, 4), 'customerId' => self::CUSTOMER_ID],
                ],
            ],
        ]);

        return $this->createdResourceDouble($mollieOrderId, 'order', $method, 'created');
    }

    /**
     * A Mollie payment resource as it comes back from payments->create().
     *
     * Also stubs the payments->get() that follows, for the same reason as createdMollieOrder().
     */
    protected function createdMolliePayment(string $molliePaymentId, string $method): object
    {
        $this->mockSuccessfulPaymentGet($molliePaymentId, 'open', [
            'method' => $method,
            'mode' => 'test',
            'customerId' => self::CUSTOMER_ID,
        ]);

        return $this->createdResourceDouble($molliePaymentId, 'payment', $method, 'open');
    }

    /**
     * The shape the processor actually consumes from a freshly created resource: the id and mode it
     * writes to the order, the resource type PaymentFactory switches on, the method
     * updatePaymentStatusForDelayedMethods() reads, and the checkout URL the redirect strategy
     * returns.
     */
    protected function createdResourceDouble(string $id, string $resource, string $method, string $status): object
    {
        $double = Mockery::mock('MollieCreated' . ucfirst($resource));
        $double->id = $id;
        $double->resource = $resource;
        $double->mode = 'test';
        $double->method = $method;
        $double->status = $status;
        $double->customerId = self::CUSTOMER_ID;
        $double->shouldReceive('getCheckoutUrl')->andReturn($this->checkoutUrl($id));

        return $double;
    }

    protected function checkoutUrl(string $resourceId): string
    {
        return 'https://www.mollie.com/checkout/' . $resourceId;
    }

    /**
     * A Mollie rejection the processor does not classify: not a 400/500 outage, not a 422 fraud
     * rejection, not a 422 invalid phone number. Those three abort the checkout with their own
     * message, so a test about the Orders → Payments fallback has to stay clear of them.
     */
    protected function unclassifiedApiException(?string $field = null): ApiException
    {
        return new ApiException('The order could not be created.', 422, $field);
    }

    /**
     * Customer resolution runs before any payment is created, and both of its branches call Mollie:
     * an id already on the user is validated with customers->get(), an absent one is created with
     * customers->create(). Stub both so no test depends on which branch the box happens to be in.
     */
    private function mockCustomerEndpoints(): void
    {
        $client = $this->apiMock()->getMockedApiClient();

        $client->customers->shouldReceive('get')
            ->andReturn((object) ['id' => self::CUSTOMER_ID])
            ->byDefault();
        $client->customers->shouldReceive('create')
            ->andReturn((object) ['id' => self::CUSTOMER_ID])
            ->byDefault();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Environment
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Pins the customer's Mollie id so `payment.customerId` in the request is a known value.
     */
    private function pinMollieCustomerId(): void
    {
        $existing = get_user_meta($this->customer_id, 'mollie_customer_id', true);
        $this->customerIdMetaBackup = is_string($existing) ? $existing : '';

        update_user_meta($this->customer_id, 'mollie_customer_id', self::CUSTOMER_ID);
    }

    /**
     * Selects the Orders API plugin-wide.
     */
    protected function useOrdersApi(): void
    {
        $this->setOptionForTest(
            'mollie-payments-for-woocommerce_api_switch',
            PaymentProcessor::PAYMENT_METHOD_TYPE_ORDER
        );
    }

    /**
     * Selects the Payments API plugin-wide, which is the plugin's own default.
     */
    protected function usePaymentsApi(): void
    {
        $this->setOptionForTest(
            'mollie-payments-for-woocommerce_api_switch',
            PaymentProcessor::PAYMENT_METHOD_TYPE_PAYMENT
        );
    }

    /**
     * Widens the list of methods Mollie reports as available so gateways outside
     * MockedApi::defaultAvailableMethods() register too. processPayment() re-initialises
     * WooCommerce's registry afterwards, so the added gateway is there when the test looks it up;
     * it then stays registered for the rest of the run, which is harmless — no test looks a gateway
     * up by absence.
     *
     * @param array<string> $methodIds
     */
    protected function registerAdditionalGateways(array $methodIds): void
    {
        $ids = array_merge(['ideal', 'banktransfer', 'creditcard', 'klarna', 'paypal'], $methodIds);

        $this->apiMock()->getMockedApiClient()->methods
            ->shouldReceive('allAvailable')
            ->andReturn(array_map(static function (string $id): \stdClass {
                return (object) ['id' => $id, 'description' => ucfirst($id), 'status' => 'activated'];
            }, $ids));

        $this->flushMollieMethodsCache();
    }
}
