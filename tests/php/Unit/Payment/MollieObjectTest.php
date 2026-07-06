<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order as MollieApiOrder;
use Mollie\Api\Resources\Payment as MollieApiPayment;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionProperty;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Payment\MollieObject
 */
class MollieObjectTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var LoggerInterface&\Mockery\MockInterface */
    private $logger;

    /** @var Settings&\Mockery\MockInterface */
    private $settings;

    /** @var Api&\Mockery\MockInterface */
    private $apiHelper;

    /** @var MollieApiClient */
    private $apiClient;

    /** @var \Mockery\MockInterface */
    private $paymentEndpoint;

    /** @var \Mockery\MockInterface */
    private $orderEndpoint;

    private MollieObject $sut;

    protected function setUp(): void
    {
        parent::setUp();

        when('wc_get_base_location')->justReturn(['country' => 'NL']);

        $this->logger          = Mockery::mock(LoggerInterface::class);
        $this->settings        = Mockery::mock(Settings::class);
        $this->apiHelper       = Mockery::mock(Api::class);
        $this->paymentEndpoint = Mockery::mock(PaymentEndpoint::class);
        $this->orderEndpoint   = Mockery::mock(OrderEndpoint::class);

        $this->logger->shouldReceive('debug')->zeroOrMoreTimes();
        $this->settings->shouldReceive('isTestModeEnabled')->andReturn(false)->byDefault();
        $this->settings->shouldReceive('getApiKey')->andReturn('test_api_key')->byDefault();

        $this->apiClient           = $this->createMock(MollieApiClient::class);
        $this->apiClient->payments = $this->paymentEndpoint;
        $this->apiClient->orders   = $this->orderEndpoint;

        $this->apiHelper
            ->shouldReceive('getApiClient')
            ->with('test_api_key')
            ->andReturn($this->apiClient)
            ->byDefault();

        $this->resetStaticCaches();

        $this->sut = new MollieObject(
            null,
            $this->logger,
            Mockery::mock(PaymentFactory::class),
            $this->apiHelper,
            $this->settings,
            'mollie-payments-for-woocommerce',
            Mockery::mock(RequestFactory::class)
        );
    }

    private function resetStaticCaches(): void
    {
        foreach (['paymentObjectCache', 'orderObjectCache'] as $property) {
            try {
                $ref = new ReflectionProperty(MollieObject::class, $property);
                if (PHP_VERSION_ID < 80100) {
                    $ref->setAccessible(true);
                }
                $ref->setValue(null, []);
            } catch (ReflectionException $e) {
                // Property does not exist yet — phase 03 will add it.
            }
        }
    }

    private function makePayment(string $id = 'tr_test123'): MollieApiPayment
    {
        $payment     = Mockery::mock(MollieApiPayment::class);
        $payment->id = $id;
        return $payment;
    }

    private function makeOrder(string $id = 'ord_test123'): MollieApiOrder
    {
        $order     = Mockery::mock(MollieApiOrder::class);
        $order->id = $id;
        return $order;
    }

    /**
     * @scenario Within a single PHP request, calling getPaymentObjectPayment with the same
     *           payment_id and use_cache=true more than once returns the result cached from
     *           the first call without making additional HTTP requests to the Mollie API.
     * @covers \Mollie\WooCommerce\Payment\MollieObject::getPaymentObjectPayment
     */
    public function test_cache_deduplicates_repeated_calls_for_same_payment_id(): void
    {
        // Arrange
        $paymentId = 'tr_cache_test';
        $payment   = $this->makePayment($paymentId);
        $this->paymentEndpoint
            ->shouldReceive('get')
            ->with($paymentId)
            ->once()
            ->andReturn($payment);

        // When
        $first  = $this->sut->getPaymentObjectPayment($paymentId, false, true);
        $second = $this->sut->getPaymentObjectPayment($paymentId, false, true);

        // Then
        $this->assertSame($payment, $first);
        $this->assertSame($payment, $second);
    }

    /**
     * @scenario When use_cache=false, getPaymentObjectPayment always calls the Mollie API,
     *           updates the static cache entry for the payment_id, and the subsequent
     *           use_cache=true call returns the fresh result without another API call.
     * @covers \Mollie\WooCommerce\Payment\MollieObject::getPaymentObjectPayment
     */
    public function test_use_cache_false_bypasses_cache_and_updates_it(): void
    {
        // Arrange
        $paymentId   = 'tr_bypass_test';
        $firstResult = $this->makePayment($paymentId);
        $freshResult = $this->makePayment($paymentId);
        $this->paymentEndpoint
            ->shouldReceive('get')
            ->with($paymentId)
            ->twice()
            ->andReturn($firstResult, $freshResult);

        // When — prime cache, then force-refresh with use_cache=false
        $this->sut->getPaymentObjectPayment($paymentId, false, true);
        $fromBypass = $this->sut->getPaymentObjectPayment($paymentId, false, false);
        $fromCache  = $this->sut->getPaymentObjectPayment($paymentId, false, true);

        // Then — bypass returned fresh value; cached call also got the updated fresh value
        $this->assertSame($freshResult, $fromBypass);
        $this->assertSame($freshResult, $fromCache);
    }

    /**
     * @scenario When the first call throws an ApiException, null is stored in the cache;
     *           subsequent use_cache=true calls within the same request return null without
     *           making further API calls.
     * @covers \Mollie\WooCommerce\Payment\MollieObject::getPaymentObjectPayment
     */
    public function test_null_is_cached_when_api_throws(): void
    {
        // Arrange
        $paymentId = 'tr_error_test';
        $this->paymentEndpoint
            ->shouldReceive('get')
            ->with($paymentId)
            ->once()
            ->andThrow(new ApiException('Connection error'));

        // When
        $first  = $this->sut->getPaymentObjectPayment($paymentId, false, true);
        $second = $this->sut->getPaymentObjectPayment($paymentId, false, true);

        // Then — both null; Mockery ->once() above asserts the API was not called a second time
        $this->assertNull($first);
        $this->assertNull($second);
    }

    /**
     * @scenario Resetting the static cache (simulating a new PHP request) causes the next
     *           call to hit the Mollie API again, even for a previously cached payment_id.
     * @covers \Mollie\WooCommerce\Payment\MollieObject::getPaymentObjectPayment
     */
    public function test_static_cache_reset_causes_fresh_api_call(): void
    {
        // Arrange
        $paymentId = 'tr_reset_test';
        $payment   = $this->makePayment($paymentId);
        $this->paymentEndpoint
            ->shouldReceive('get')
            ->with($paymentId)
            ->twice()
            ->andReturn($payment);

        // When — first call populates cache; reset simulates new PHP request; second call must hit API
        $this->sut->getPaymentObjectPayment($paymentId, false, true);
        $this->resetStaticCaches();
        $this->sut->getPaymentObjectPayment($paymentId, false, true);

        // Then — Mockery ->twice() above verifies the API was called exactly twice
    }

    /**
     * @scenario getPaymentObjectOrder deduplicates calls for the same order_id using its own
     *           static cache, independent of the payment cache.
     * @covers \Mollie\WooCommerce\Payment\MollieObject::getPaymentObjectOrder
     */
    public function test_get_payment_object_order_applies_same_caching(): void
    {
        // Arrange
        $orderId = 'ord_cache_test';
        $order   = $this->makeOrder($orderId);
        $this->orderEndpoint
            ->shouldReceive('get')
            ->with($orderId, ['embed' => 'payments'])
            ->once()
            ->andReturn($order);

        // When
        $first  = $this->sut->getPaymentObjectOrder($orderId, false, true);
        $second = $this->sut->getPaymentObjectOrder($orderId, false, true);

        // Then
        $this->assertSame($order, $first);
        $this->assertSame($order, $second);
    }
}
