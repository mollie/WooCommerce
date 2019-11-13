<?php

namespace MollieTests\Functional\Wc\Payment;

use Brain\Monkey\Expectation\Exception\ExpectationArgsRequired;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Refund;
use Mollie_WC_Helper_Data;
use Mollie\WC\Payment\OrderItemsRefunder;
use MollieTests\TestCase;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_MockObject_RuntimeException;
use Mollie\WC\Payment\RefundLineItemsBuilder;
use stdClass;
use UnexpectedValueException;
use WC_Order;
use WC_Order_Item;
use function Brain\Monkey\Actions\expectDone as expectedActionDone;
use function Brain\Monkey\Functions\when;

/**
 * Class OrderItemsRefunderTest
 * @package Mollie\WooCommerce\Tests\Unit
 */
class OrderItemsRefunderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RefundLineItemsBuilder
     */
    private $refundLineItemsBuilder;

    /**
     * @var Mollie_WC_Helper_Data
     */
    private $dataHelper;

    /**
     * @var OrderEndpoint
     */
    private $ordersApiClient;

    /**
     * @throws ApiException
     * @throws PHPUnit_Framework_Exception
     * @throws PHPUnit_Framework_MockObject_RuntimeException
     * @throws UnexpectedValueException
     * @throws ExpectationArgsRequired
     */
    public function testRefund()
    {
        /*
         * Stubs
         */
        $order = new WC_Order();
        /** @var WC_Order_Item $orderItem */
        $orderItem = $this->orderItem(['meta' => uniqid()]);
        $orderLineItem = $this->orderLineItem(
            [
                'metadata' => ['order_item_id' => $orderItem->get_meta()],
                'id' => uniqid(),
            ]
        );
        $remoteOrder = $this->remoteOrder([$orderLineItem]);
        $refundReason = uniqid();
        $lineItems = $this->buildLineItems($refundReason);
        $refund = \Mockery::type(Refund::class);

        /*
         * Sut
         */
        $orderItemsRefunder = new OrderItemsRefunder(
            $this->refundLineItemsBuilder,
            $this->dataHelper,
            $this->ordersApiClient
        );

        /*
         * Stubbing
         */
        $this->refundLineItemsBuilder
            ->method('buildLineItems')
            ->willReturn($lineItems);

        $this->ordersApiClient
            ->method('get')
            ->willReturn($remoteOrder);

        /*
         * Expectations
         */
        $remoteOrder
            ->expects($this->once())
            ->method('cancelLines')
            ->with($lineItems['toCancel']);

        $remoteOrder
            ->expects($this->once())
            ->method('refund')
            ->with($lineItems['toRefund'])
            ->willReturn($refund);

        expectedActionDone(OrderItemsRefunder::ACTION_AFTER_CANCELED_ORDER_ITEMS)
            ->once()
            ->with($lineItems['toCancel'], $order);

        expectedActionDone(OrderItemsRefunder::ACTION_AFTER_REFUND_ORDER_ITEMS)
            ->once()
            ->with($refund, $order, $lineItems['toRefund']);

        /*
         * Execute Test
         */
        $orderItemsRefunder->refund($order, [$orderItem], $remoteOrder, $refundReason);
    }

    /**
     * @throws ApiException
     * @throws PHPUnit_Framework_Exception
     * @throws PHPUnit_Framework_MockObject_RuntimeException
     * @throws UnexpectedValueException
     */
    public function testRefundDoesNotRefundNorCancelOrderLineItems()
    {
        /*
         * Stubs
         */
        $order = new WC_Order();
        /** @var WC_Order_Item $orderItem */
        $orderItem = $this->orderItem(['meta' => uniqid()]);
        $orderLineItem = $this->orderLineItem(
            [
                'metadata' => ['order_item_id' => $orderItem->get_meta()],
                'id' => uniqid(),
            ]
        );
        /** @var Order $remoteOrder */
        $remoteOrder = $this->remoteOrder([$orderLineItem]);
        $refundReason = uniqid();
        $lineItems = $this->buildLineItems($refundReason);

        /*
         * Sut
         */
        $orderItemsRefunder = new OrderItemsRefunder(
            $this->refundLineItemsBuilder,
            $this->dataHelper,
            $this->ordersApiClient
        );

        /*
         * Stubbing
         */

        /*
         * Force lines for cancel and refund items to be empty.
         * This should prevent to call `cancelLines` and `refund`
         */
        $lineItems['toCancel']['lines'] = [];
        $lineItems['toRefund']['lines'] = [];
        $this->refundLineItemsBuilder->method('buildLineItems')->willReturn($lineItems);

        $this->ordersApiClient->method('get')->willReturn($remoteOrder);

        /*
         * Expectations
         */
        $remoteOrder->expects($this->never())->method('cancelLines');
        $remoteOrder->expects($this->never())->method('refund');

        expectedActionDone(OrderItemsRefunder::ACTION_AFTER_CANCELED_ORDER_ITEMS)->never();
        expectedActionDone(OrderItemsRefunder::ACTION_AFTER_REFUND_ORDER_ITEMS)->never();

        /*
         * Execute Test
         */
        $orderItemsRefunder->refund($order, [$orderItem], $remoteOrder, $refundReason);
    }

    /**
     * @throws ApiException
     * @throws UnexpectedValueException
     * @throws PHPUnit_Framework_Exception
     */
    public function testUnexpectedValueExceptionWhenBuildRefundItems()
    {
        /*
         * Stubs
         */
        $order = new WC_Order();
        // Passing null is the key here, this will throw the exception because of invalid value.
        /** @var WC_Order_Item $orderItem */
        $orderItem = $this->orderItem(['meta' => null]);
        $orderLineItem = $this->orderLineItem(
            [
                'metadata' => ['order_item_id' => $orderItem->get_meta()],
                'id' => uniqid(),
            ]
        );
        $remoteOrder = $this->remoteOrder([$orderLineItem]);
        $refundReason = uniqid();

        /*
         * Sut
         */
        $orderItemsRefunder = new OrderItemsRefunder(
            $this->refundLineItemsBuilder,
            $this->dataHelper,
            $this->ordersApiClient
        );

        /*
         * Expectations
         */
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'One of the WooCommerce order items does not have the refund item id meta value associated to Mollie Order item.'
        );

        /*
         * Execute Test
         */
        $orderItemsRefunder->refund($order, [$orderItem], $remoteOrder, $refundReason);
    }

    /**
     * @throws ApiException
     * @throws PHPUnit_Framework_Exception
     * @throws UnexpectedValueException
     */
    public function testUnexpectedValueExceptionWhenBuildRemoteItems()
    {
        /*
         * Stubs
         */
        $order = new WC_Order();
        /** @var WC_Order_Item $orderItem */
        $orderItem = $this->orderItem(['meta' => uniqid()]);
        $orderLineItem = $this->orderLineItem(
            [
                'metadata' => null,
                'id' => uniqid(),
            ]
        );
        $remoteOrder = $this->remoteOrder([$orderLineItem]);
        $refundReason = uniqid();

        /*
         * Sut
         */
        $orderItemsRefunder = new OrderItemsRefunder(
            $this->refundLineItemsBuilder,
            $this->dataHelper,
            $this->ordersApiClient
        );

        /*
        * Expectations
        */
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Impossible to retrieve the order item id related to the remote item: {$orderLineItem->id}. Try to do a refund by amount."
        );

        /*
         * Execute Test
         */
        $orderItemsRefunder->refund($order, [$orderItem], $remoteOrder, $refundReason);
    }

    /**
     * @throws ApiException
     * @throws PHPUnit_Framework_Exception
     * @throws UnexpectedValueException
     */
    public function testBailIfNoItemsToRefund()
    {
        /*
         * Stubs
         */
        $order = new WC_Order();
        $remoteOrder = $this->remoteOrder([]);
        $refundReason = uniqid();

        /*
         * Sut
         */
        $orderItemsRefunder = new OrderItemsRefunder(
            $this->refundLineItemsBuilder,
            $this->dataHelper,
            $this->ordersApiClient
        );

        /*
        * Expectations
        */
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Empty woocommerce order items or mollie order lines.');

        /*
         * Execute Test
         */
        $orderItemsRefunder->refund($order, [], $remoteOrder, $refundReason);
    }

    /**
     * @param array $config
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function orderItem(array $config)
    {
        $item = $this->createConfiguredMock(
            'WC_Order_Item',
            [
                'get_meta' => $config['meta'],
            ]
        );

        return $item;
    }

    /**
     * @param array $config
     * @return stdClass
     */
    private function orderLineItem(array $config)
    {
        $orderLineItem = new stdClass();
        $orderLineItem->metadata = (object)$config['metadata'];
        $orderLineItem->id = $config['id'];

        return $orderLineItem;
    }

    /**
     * @param array $orderLineItems
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function remoteOrder(array $orderLineItems)
    {
        $mock = $this
            ->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods(['cancelLines', 'refund'])
            ->getMock();

        $mock->lines = $orderLineItems;

        return $mock;
    }

    /**
     * @param $refundReason
     * @return array
     */
    private function buildLineItems($refundReason)
    {
        return [
            'toCancel' => [
                'description' => $refundReason,
                'lines' => [true],
            ],
            'toRefund' => [
                'description' => $refundReason,
                'lines' => [true],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        when('__')->returnArg(1);

        $this->initializeDependencies();
    }

    /**
     * @return void
     */
    private function initializeDependencies()
    {
        $this->refundLineItemsBuilder = $this->refundLineItemsBuilder();
        $this->dataHelper = $this->dataHelper();
        $this->ordersApiClient = $this->ordersApiClient();
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function refundLineItemsBuilder()
    {
        $mock = $this
            ->getMockBuilder(RefundLineItemsBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['buildLineItems'])
            ->getMock();

        return $mock;
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function dataHelper()
    {
        $mock = $this
            ->getMockBuilder(Mollie_WC_Helper_Data::class)
            ->disableOriginalConstructor()
            ->setMethods(['getOrderCurrency'])
            ->getMock();

        return $mock;
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function ordersApiClient()
    {
        $mock = $this
            ->getMockBuilder(OrderEndpoint::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();

        return $mock;
    }
}
