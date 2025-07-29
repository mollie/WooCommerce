<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\API\Traits;

use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerceTests\Integration\API\Mocks\MockedApi;
use Mollie\Api\Exceptions\ApiException;

trait APIMockTrait
{
    /**
     * @var MockedApi
     */
    protected $apiMock;

    /**
     * Initialize the API mock
     */
    protected function initializeApiMock(): void
    {
        $this->apiMock = new MockedApi();
    }

    /**
     * Get the API mock instance
     */
    protected function apiMock(): MockedApi
    {
        if (!$this->apiMock) {
            $this->initializeApiMock();
        }

        return $this->apiMock;
    }

    /**
     * Create a basic payment response structure for webhook testing
     *
     * @param string $paymentId
     * @param string $status
     * @param string $amount
     * @param string $currency
     * @param array $additionalData
     * @return array
     */
    protected function createPaymentResponse(
        string $paymentId,
        string $status = 'paid',
        string $amount = '10.00',
        string $currency = 'EUR',
        array $additionalData = []
    ): array {
        $response = array_merge([
                               'id' => $paymentId,
                               'status' => $status,
                               'amount' => [
                                   'value' => $amount,
                                   'currency' => $currency
                               ],
                               'description' => 'Test payment',
                               'redirectUrl' => 'https://example.com/return',
                               'webhookUrl' => 'https://example.com/webhook',
                               'metadata' => (object)['order_id' => '1'],
                               'createdAt' => '2023-01-01T12:00:00+00:00',
                               'method' => 'ideal',
                               'mode' => 'test',
                               'resource' => 'payment',
                               // Add methods that the webhook logic expects
                               'isPaid' => function() use ($status) { return $status === 'paid'; },
                               'isAuthorized' => function() use ($status) { return $status === 'authorized'; },
                               'isFailed' => function() use ($status) { return $status === 'failed'; },
                               'isCanceled' => function() use ($status) { return $status === 'canceled'; },
                               'isExpired' => function() use ($status) { return $status === 'expired'; },
                               'isPending' => function() use ($status) { return $status === 'pending'; },
                               // Add links for refunds and chargebacks
                               '_links' => [
                                   'refunds' => null,
                                   'chargebacks' => null
                               ],
                               '_embedded' => [
                                   'refunds' => []
                               ],
                               'amountRefunded' => null,
                               'amountChargedBack' => null
                           ], $additionalData);
        if (isset($additionalData['metadata']) && is_array($additionalData['metadata'])) {
            $response['metadata'] = (object)$additionalData['metadata'];
        }
        return $response;
    }

    /**
     * Create a basic customer response structure
     *
     * @param string $customerId
     * @param string $email
     * @param array $additionalData
     * @return array
     */
    protected function createCustomerResponse(
        string $customerId,
        string $email = 'test@example.com',
        array $additionalData = []
    ): array {
        return array_merge([
                               'id' => $customerId,
                               'email' => $email,
                               'name' => 'Test Customer',
                               'createdAt' => '2023-01-01T12:00:00+00:00'
                           ], $additionalData);
    }

    /**
     * Create a basic order response structure
     *
     * @param string $orderId
     * @param string $status
     * @param array $additionalData
     * @return array
     */
    protected function createOrderResponse(
        string $orderId,
        string $status = 'created',
        array $additionalData = []
    ): array {
        return array_merge([
                               'id' => $orderId,
                               'status' => $status,
                               'amount' => [
                                   'value' => '10.00',
                                   'currency' => 'EUR'
                               ],
                               'lines' => [],
                               'createdAt' => '2023-01-01T12:00:00+00:00'
                           ], $additionalData);
    }

    /**
     * Mock a successful payment retrieval
     *
     * @param string $paymentId
     * @param string $status
     * @param array $additionalData
     */
    protected function mockSuccessfulPaymentGet(
        string $paymentId,
        string $status = 'paid',
        array $additionalData = []
    ): void {
        $payment = $this->createPaymentResponse($paymentId, $status, '10.00', 'EUR', $additionalData);
        $this->apiMock()->mockPaymentGet($paymentId, $payment);
    }

    /**
     * Mock a failed payment retrieval
     *
     * @param string $paymentId
     * @param int $errorCode
     * @param string $errorMessage
     * @throws ApiException
     */
    protected function mockFailedPaymentGet(
        string $paymentId,
        int $errorCode = 404,
        string $errorMessage = 'Payment not found'
    ): void {
        $this->apiMock()->mockEndpointException(
            'payments',
            'get',
            [$paymentId],
            new ApiException($errorMessage, $errorCode)
        );
    }

    /**
     * Mock a successful payment creation
     *
     * @param array $paymentParams
     * @param string $paymentId
     * @param string $status
     */
    protected function mockSuccessfulPaymentCreate(
        array $paymentParams,
        string $paymentId = 'tr_test_payment_id',
        string $status = 'open'
    ): void {
        $response = $this->createPaymentResponse($paymentId, $status);
        $response['_links'] = [
            'checkout' => [
                'href' => 'https://mollie.com/checkout/' . $paymentId,
                'type' => 'text/html'
            ]
        ];

        $this->apiMock()->mockPaymentCreate($paymentParams, $response);
    }

    /**
     * Reset all API mocks
     */
    protected function resetApiMocks(): void
    {
        if ($this->apiMock) {
            $this->apiMock->resetMocks();
        }
    }

    /**
     * Get mocked services array for bootstrapping
     *
     * @return array
     */
    protected function getMockedApiServices(): array
    {
        return [
            'SDK.api_helper' => function () {
                return $this->apiMock();
            }
        ];
    }
}
