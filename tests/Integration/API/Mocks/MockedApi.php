<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\API\Mocks;

use Mollie\Api\Endpoints\ChargebackEndpoint;
use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\Api\Endpoints\InvoiceEndpoint;
use Mollie\Api\Endpoints\MandateEndpoint;
use Mollie\Api\Endpoints\MethodEndpoint;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\OrganizationEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Endpoints\PermissionEndpoint;
use Mollie\Api\Endpoints\ProfileEndpoint;
use Mollie\Api\Endpoints\RefundEndpoint;
use Mollie\Api\Endpoints\SettlementsEndpoint;
use Mollie\Api\Endpoints\SubscriptionEndpoint;
use Mollie\WooCommerce\SDK\Api;
use Mollie\Api\MollieApiClient;
use Mockery;

class MockedApi extends Api
{
    /**
     * @var \Mockery\MockInterface|\Mollie\Api\MollieApiClient
     */
    protected $mockedApiClient;

    /**
     * @var array
     */
    protected $mockedResponses = [];

    public function __construct(string $pluginVersion = '1.0.0', string $pluginId = 'mollie-test')
    {
        parent::__construct($pluginVersion, $pluginId);
        $this->setupMockedApiClient();
    }

    /**
     * Override the parent method to return our mocked client
     *
     * @param string $apiKey
     * @param bool $needToUpdateApiKey
     * @return \Mockery\MockInterface|\Mollie\Api\MollieApiClient
     */
    public function getApiClient($apiKey, $needToUpdateApiKey = false)
    {
        if (empty($apiKey)) {
            throw new \Mollie\Api\Exceptions\ApiException('No API key provided. Please set your Mollie API keys below.');
        } elseif (!preg_match('#^(live|test)_\w{30,}$#', $apiKey)) {
            throw new \Mollie\Api\Exceptions\ApiException('Invalid API key format.');
        }

        return $this->mockedApiClient;
    }

    /**
     * Setup the mocked API client with all necessary endpoints
     */
    protected function setupMockedApiClient(): void
    {
        $this->mockedApiClient = Mockery::mock(MollieApiClient::class);

        $this->mockedApiClient->payments = Mockery::mock(PaymentEndpoint::class);
        $this->mockedApiClient->methods = Mockery::mock(MethodEndpoint::class);
        $this->mockedApiClient->customers = Mockery::mock(CustomerEndpoint::class);
        $this->mockedApiClient->orders = Mockery::mock(OrderEndpoint::class);
        $this->mockedApiClient->refunds = Mockery::mock(RefundEndpoint::class);
        $this->mockedApiClient->subscriptions = Mockery::mock(SubscriptionEndpoint::class);
        $this->mockedApiClient->mandates = Mockery::mock(MandateEndpoint::class);
        $this->mockedApiClient->profiles = Mockery::mock(ProfileEndpoint::class);
        $this->mockedApiClient->organizations = Mockery::mock(OrganizationEndpoint::class);
        $this->mockedApiClient->permissions = Mockery::mock(PermissionEndpoint::class);
        $this->mockedApiClient->invoices = Mockery::mock(InvoiceEndpoint::class);
        $this->mockedApiClient->settlements = Mockery::mock(SettlementsEndpoint::class);
        $this->mockedApiClient->chargebacks = Mockery::mock(ChargebackEndpoint::class);
    }

    /**
     * Configure mock responses for payments endpoint
     *
     * @param string $paymentId
     * @param array $paymentData
     * @return self
     */
    public function mockPaymentGet(string $paymentId, array $paymentData): self
    {
        $paymentObject = $this->createMockPaymentObject($paymentData);

        $this->mockedApiClient->payments
            ->shouldReceive('get')
            ->with($paymentId)
            ->andReturn($paymentObject);

        return $this;
    }

    /**
     * Create a mock payment object that mimics Mollie Payment resource behavior
     *
     * @param array $paymentData
     * @return \Mockery\MockInterface
     */
    protected function createMockPaymentObject(array $paymentData)
    {
        $paymentObject = Mockery::mock('Payment');

        foreach ($paymentData as $key => $value) {
            $paymentObject->$key = $value;
        }

        $status = $paymentData['status'] ?? 'pending';
        $paymentObject->shouldReceive('isPaid')->andReturn($status === 'paid');
        $paymentObject->shouldReceive('isAuthorized')->andReturn($status === 'authorized');
        $paymentObject->shouldReceive('isFailed')->andReturn($status === 'failed');
        $paymentObject->shouldReceive('isCanceled')->andReturn($status === 'canceled');
        $paymentObject->shouldReceive('isExpired')->andReturn($status === 'expired');
        $paymentObject->shouldReceive('isPending')->andReturn($status === 'pending');
        $paymentObject->shouldReceive('isCompleted')->andReturn($status === 'completed');

        $paymentObject->shouldReceive('refunds')->andReturn([]);
        $paymentObject->shouldReceive('chargebacks')->andReturn([]);

        return $paymentObject;
    }

    /**
     * Configure mock responses for payment creation
     *
     * @param array $paymentParams
     * @param array $paymentResponse
     * @return self
     */
    public function mockPaymentCreate(array $paymentParams, array $paymentResponse): self
    {
        $this->mockedApiClient->payments
            ->shouldReceive('create')
            ->with($paymentParams)
            ->andReturn((object)$paymentResponse);

        return $this;
    }

    /**
     * Configure mock responses for payment updates
     *
     * @param string $paymentId
     * @param array $updateData
     * @param array $updatedPaymentResponse
     * @return self
     */
    public function mockPaymentUpdate(string $paymentId, array $updateData, array $updatedPaymentResponse): self
    {
        $this->mockedApiClient->payments
            ->shouldReceive('update')
            ->with($paymentId, $updateData)
            ->andReturn((object)$updatedPaymentResponse);

        return $this;
    }

    /**
     * Configure mock responses for customer creation
     *
     * @param array $customerData
     * @param array $customerResponse
     * @return self
     */
    public function mockCustomerCreate(array $customerData, array $customerResponse): self
    {
        $this->mockedApiClient->customers
            ->shouldReceive('create')
            ->with($customerData)
            ->andReturn((object)$customerResponse);

        return $this;
    }

    /**
     * Configure mock responses for customer get
     *
     * @param string $customerId
     * @param array $customerData
     * @return self
     */
    public function mockCustomerGet(string $customerId, array $customerData): self
    {
        $this->mockedApiClient->customers
            ->shouldReceive('get')
            ->with($customerId)
            ->andReturn((object)$customerData);

        return $this;
    }

    /**
     * Configure mock responses for order creation
     *
     * @param array $orderData
     * @param array $orderResponse
     * @return self
     */
    public function mockOrderCreate(array $orderData, array $orderResponse): self
    {
        $this->mockedApiClient->orders
            ->shouldReceive('create')
            ->with($orderData)
            ->andReturn((object)$orderResponse);

        return $this;
    }

    /**
     * Configure mock responses for order get
     *
     * @param string $orderId
     * @param array $orderData
     * @return self
     */
    public function mockOrderGet(string $orderId, array $orderData): self
    {
        $this->mockedApiClient->orders
            ->shouldReceive('get')
            ->with($orderId)
            ->andReturn((object)$orderData);

        return $this;
    }

    /**
     * Configure mock responses for refund creation
     *
     * @param array $refundData
     * @param array $refundResponse
     * @return self
     */
    public function mockRefundCreate(array $refundData, array $refundResponse): self
    {
        $this->mockedApiClient->refunds
            ->shouldReceive('create')
            ->with($refundData)
            ->andReturn((object)$refundResponse);

        return $this;
    }

    /**
     * Configure mock for any method call with flexible parameters
     *
     * @param string $endpoint (e.g., 'payments', 'customers', 'orders')
     * @param string $method (e.g., 'get', 'create', 'update', 'delete')
     * @param array $expectedParams
     * @param mixed $response
     * @return self
     */
    public function mockEndpointCall(string $endpoint, string $method, array $expectedParams, $response): self
    {
        $mockCall = $this->mockedApiClient->$endpoint->shouldReceive($method);

        if (!empty($expectedParams)) {
            $mockCall->with(...$expectedParams);
        }

        $mockCall->andReturn(is_array($response) ? (object)$response : $response);

        return $this;
    }

    /**
     * Configure mock to throw an exception
     *
     * @param string $endpoint
     * @param string $method
     * @param array $expectedParams
     * @param \Exception $exception
     * @return self
     */
    public function mockEndpointException(string $endpoint, string $method, array $expectedParams, \Exception $exception): self
    {
        $mockCall = $this->mockedApiClient->$endpoint->shouldReceive($method);

        if (!empty($expectedParams)) {
            $mockCall->with(...$expectedParams);
        }

        $mockCall->andThrow($exception);

        return $this;
    }

    /**
     * Get the mocked API client for direct manipulation in tests
     *
     * @return \Mockery\MockInterface|\Mollie\Api\MollieApiClient
     */
    public function getMockedApiClient()
    {
        return $this->mockedApiClient;
    }

    /**
     * Reset all mock expectations (useful between tests)
     */
    public function resetMocks(): void
    {
        Mockery::close();
        $this->setupMockedApiClient();
    }
}
