<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mockery;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Integration tests for PaymentProcessor handleUnprocessablePhone method
 *
 * @group integration
 * @group payment-processor
 * @covers PaymentProcessor::handleUnprocessablePhone
 */
class PaymentProcessorUnprocessablePhoneTest extends IntegrationMockedTestCase
{
    use APIMockTrait;

    private PaymentProcessor $paymentProcessor;
    private WC_Order $order;
    private PaymentGateway $paymentGateway;

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();
        $gatewayId ='mollie_wc_gateway_ideal';
        // Create real test order
        $this->order = $this->getConfiguredOrder(
            0, // guest customer
            $gatewayId,
            ['simple'],
            [],
            false // don't set as paid
        );

        // Set invalid phone number to trigger the exception
        $this->order->set_billing_phone('invalid_phone_123');
        $this->order->save();

        // Bootstrap module with mocked API services
        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);
        $this->paymentGateway = new PaymentGateway($gatewayId, $container);
    }

    public function tearDown(): void
    {
        $this->resetApiMocks();
        parent::tearDown();
    }

    /**
     * @test
     * @scenario Customer checkout fails due to invalid phone number
     *
     * Given a customer places an order with an invalid phone number
     * When the payment processor attempts to create the order via Mollie API
     * And the Mollie API returns a 422 unprocessable entity error for invalid phone
     * Then the system should log a debug message about the invalid phone number
     * And the payment processing should return a failure result
     * And the system should handle the error gracefully without throwing exceptions
     */
    public function payment_processing_fails_gracefully_when_customer_provides_invalid_phone_number(): void
    {
        $unprocessablePhoneException = new ApiException(
            'Unprocessable Entity: The phone number is invalid',
            422
        );

        $logger = Mockery::mock(LoggerInterface::class);
        $logMessages = [];

        $logger->shouldReceive('debug')
            ->withAnyArgs()
            ->andReturnUsing(function($message) use (&$logMessages) {
                $logMessages[] = $message;
            });

        $mockedServices = $this->getMockedApiServices();
        $mockedServices[LoggerInterface::class] = function () use ($logger) {
            return $logger;
        };

        $container = $this->bootstrapModule($mockedServices);
        $paymentProcessor = $container->get(PaymentProcessor::class);
        $this->apiMock()
            ->mockEndpointException('orders', 'create', [], $unprocessablePhoneException);

        $result = $paymentProcessor->processPayment($this->order, $this->paymentGateway);
        $phoneMessageFound = false;
        foreach ($logMessages as $message) {
            if (strpos($message, 'Invalid phone number') !== false) {
                $phoneMessageFound = true;
                break;
            }
        }
        $this->assertTrue($phoneMessageFound, 'Phone invalid message was not logged');

        $this->assertEquals(['result' => 'failure'], $result);
    }


    /**
     * @test
     * @scenario Payment processing handles non-phone validation errors correctly
     *
     * Given a customer places an order
     * When the payment processor attempts to create the order via Mollie API
     * And the Mollie API returns a 422 unprocessable entity error for a non-phone validation issue
     * Then the system should not log any phone-specific debug messages
     * And the payment processing should return a failure result
     * And the system should not apply phone-specific error handling
     */
    public function payment_processing_ignores_phone_handler_for_non_phone_validation_errors(): void
    {
        $otherException = new ApiException(
            'Some other error message',
            422
        );
        $logger = Mockery::mock(LoggerInterface::class);
        $logMessages = [];

        $logger->shouldReceive('debug')
            ->withAnyArgs()
            ->andReturnUsing(function($message) use (&$logMessages) {
                $logMessages[] = $message;
            });

        $mockedServices = $this->getMockedApiServices();
        $mockedServices[LoggerInterface::class] = function () use ($logger) {
            return $logger;
        };

        $container = $this->bootstrapModule($mockedServices);
        $paymentProcessor = $container->get(PaymentProcessor::class);
        $this->apiMock()->getMockedApiClient()->orders
            ->shouldReceive('create')
            ->andThrow($otherException);
        //it will fallback to payments API
        $this->apiMock()->getMockedApiClient()->payments
            ->shouldReceive('create')
            ->andThrow($otherException);

        $result = $paymentProcessor->processPayment($this->order, $this->paymentGateway);
        $phoneMessageFound = false;
        foreach ($logMessages as $message) {
            if (strpos($message, 'Invalid phone number') !== false) {
                $phoneMessageFound = true;
                break;
            }
        }
        $this->assertFalse($phoneMessageFound, 'Phone invalid message was logged');

        $this->assertEquals(['result' => 'failure'], $result);
    }
}
