<?php # -*- coding: utf-8 -*-

namespace MollieTests\Functional\Wc\Payment;

use Mollie_WC_Helper_Data;
use Mollie\OrderLineStatus;
use Mollie\WC\Payment\PartialRefundException;
use MollieTests\TestCase;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use Mollie\WC\Payment\RefundLineItemsBuilder;
use stdClass;
use UnexpectedValueException;
use WC_Order_Item;
use function Brain\Monkey\Functions\when;

/**
 * Class RefundLineItemsBuilderTest
 * @package Mollie\WooCommerce\Tests\Unit
 */
class RefundLineItemsBuilderTest extends TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mollie_WC_Helper_Data
     */
    private $dataHelper;

    /**
     * Expect to build correct line items to perform the api call for refund
     *
     * @throws PHPUnit_Framework_Exception
     * @throws PartialRefundException
     * @throws UnexpectedValueException
     */
    public function testBuildlLineItems()
    {
        /*
         * Stubs
         */
        $reason = uniqid();
        $currency = uniqid();

        /** @var WC_Order_Item $toCancelItem */
        $toCancelItem = $this->wooCommerceOrderItem(-1, mt_rand(-100, -1), 0);
        $toCancelRemoteItem = $this->orderLineItem(
            uniqid(),
            OrderLineStatus::CAN_BE_CANCELED[0],
            [
                'value' => abs($toCancelItem->get_total()),
                'currency' => $currency,
            ],
            [
                'value' => 1,
                'currency' => $currency,
            ]
        );

        /** @var WC_Order_Item $toRefundItem */
        $toRefundItem = $this->wooCommerceOrderItem(-1, mt_rand(-100, -1), 0);
        $toRefundRemoteItem = $this->orderLineItem(
            uniqid(),
            OrderLineStatus::CAN_BE_REFUNDED[0],
            [
                'value' => abs($toRefundItem->get_total()),
                'currency' => $currency,
            ],
            [
                'value' => 1,
                'currency' => $currency,
            ]
        );
        $toRefundRemoteItems = [$toCancelRemoteItem, $toRefundRemoteItem];
        $toRefundItems = [$toCancelItem, $toRefundItem];

        /*
         * Sut
         */
        $refundLineItemsBuilder = new RefundLineItemsBuilder(
            $this->dataHelper
        );

        $this->dataHelper->method('formatCurrencyValue')->willReturn(1);

        /*
         * Execute Test
         */
        $result = $refundLineItemsBuilder->buildLineItems(
            $toRefundRemoteItems,
            $toRefundItems,
            $currency,
            $reason
        );

        $this->assertEquals(
            [
                'toCancel' => [
                    'description' => $reason,
                    'lines' => [
                        [
                            'id' => $toCancelRemoteItem->id,
                            'quantity' => abs($toCancelItem->get_quantity()),
                            'amount' => [
                                'value' => 1,
                                'currency' => $currency,
                            ],
                        ],
                    ],
                ],
                'toRefund' => [
                    'description' => $reason,
                    'lines' => [
                        [
                            'id' => $toRefundRemoteItem->id,
                            'quantity' => abs($toRefundItem->get_quantity()),
                            'amount' => [
                                'value' => 1,
                                'currency' => $currency,
                            ],
                        ],
                    ],
                ],
            ],
            $result
        );
    }

    /**
     * @throws PHPUnit_Framework_Exception
     * @throws PartialRefundException
     * @throws UnexpectedValueException
     */
    public function testBuildLineItemsSkipItemBecauseNoRefundAmountSpecified()
    {
        /*
         * Stubs
         */
        $reason = uniqid();
        $currency = uniqid();

        // Will generate a $toRefundItemAmount equal to zero.
        $toRefundItem = $this->wooCommerceOrderItem(-1, 0, 0);
        $toRefundRemoteItem = $this->orderLineItem(
            uniqid(),
            OrderLineStatus::CAN_BE_REFUNDED[0],
            [
                'value' => abs($toRefundItem->get_total()),
                'currency' => $currency,
            ]
        );
        $toRefundRemoteItems = [$toRefundRemoteItem];
        $toRefundItems = [$toRefundItem];

        /*
         * Sut
         */
        $refundLineItemsBuilder = new RefundLineItemsBuilder(
            $this->dataHelper
        );

        /*
         * Execute Test
         */
        $result = $refundLineItemsBuilder->buildLineItems(
            $toRefundRemoteItems,
            $toRefundItems,
            $currency,
            $reason
        );

        $this->assertEquals(true, empty($result['toRefund']['lines']));
    }

    /**
     * @throws PHPUnit_Framework_Exception
     * @throws PartialRefundException
     * @throws UnexpectedValueException
     */
    public function testBuildLineItemsSkipItemBecauseNoRefundItemQuantity()
    {
        /*
         * Stubs
         */
        $reason = uniqid();
        $currency = uniqid();

        // Will generate a $toRefundItemQuantity equal to zero.
        $toRefundItem = $this->wooCommerceOrderItem(0, mt_rand(-100, -1), 0);
        $toRefundRemoteItem = $this->orderLineItem(
            uniqid(),
            OrderLineStatus::CAN_BE_REFUNDED[0],
            [
                'value' => abs($toRefundItem->get_total()),
                'currency' => $currency,
            ]
        );
        $toRefundRemoteItems = [$toRefundRemoteItem];
        $toRefundItems = [$toRefundItem];

        /*
         * Sut
         */
        $refundLineItemsBuilder = new RefundLineItemsBuilder(
            $this->dataHelper
        );

        /*
         * Execute Test
         */
        $result = $refundLineItemsBuilder->buildLineItems(
            $toRefundRemoteItems,
            $toRefundItems,
            $currency,
            $reason
        );

        $this->assertEquals(true, empty($result['toRefund']['lines']));
    }

    /**
     * @throws PHPUnit_Framework_Exception
     * @throws PartialRefundException
     * @throws UnexpectedValueException
     */
    public function testBuildLineItemsSkipItemBecauseNoRemoteItemPrice()
    {
        /*
         * Stubs
         */
        $reason = uniqid();
        $currency = uniqid();

        $toRefundItem = $this->wooCommerceOrderItem(-1, mt_rand(-100, -1), 0);
        $toRefundRemoteItem = $this->orderLineItem(
            uniqid(),
            OrderLineStatus::CAN_BE_REFUNDED[0],
            [
                // Will generate a order line item price equal to zero.
                'value' => 0,
                'currency' => $currency,
            ]
        );
        $toRefundRemoteItems = [$toRefundRemoteItem];
        $toRefundItems = [$toRefundItem];

        /*
         * Sut
         */
        $refundLineItemsBuilder = new RefundLineItemsBuilder(
            $this->dataHelper
        );

        /*
         * Execute Test
         */
        $result = $refundLineItemsBuilder->buildLineItems(
            $toRefundRemoteItems,
            $toRefundItems,
            $currency,
            $reason
        );

        $this->assertEquals(true, empty($result['toRefund']['lines']));
    }

    /**
     * When a refund for order items is performed Mollie need to have a match against the refunded
     * item value and the order item price stored in the Mollie service.
     *
     * If this value does not match a PartialRefundException is thrown
     *
     * @throws PartialRefundException
     * @throws UnexpectedValueException
     * @throws PHPUnit_Framework_Exception
     */
    public function testBuildLineItemsThrowPartialRefundExceptionBecauseRefundPriceDoesNotMatch()
    {
        /*
         * Stubs
         */
        $reason = uniqid();
        $currency = uniqid();

        $toRefundItem = $this->wooCommerceOrderItem(-1, mt_rand(-100, -1), 0);
        $toRefundRemoteItem = $this->orderLineItem(
            uniqid(),
            OrderLineStatus::CAN_BE_REFUNDED[0],
            [
                // The value here do the trick, infact the value to refund is different than the remote item price.
                'value' => mt_rand(101, 200),
                'currency' => uniqid(),
            ]
        );
        $toRefundRemoteItems = [$toRefundRemoteItem];
        $toRefundItems = [$toRefundItem];

        /*
         * Sut
         */
        $refundLineItemsBuilder = new RefundLineItemsBuilder(
            $this->dataHelper
        );

        $this->expectException(PartialRefundException::class);
        $this->expectExceptionMessage(
            'Mollie doesn\'t allow a partial refund of the full amount or quantity of at least one order line. Trying to process this as an amount refund instead.'
        );

        /*
         * Execute Test
         */
        $refundLineItemsBuilder->buildLineItems(
            $toRefundRemoteItems,
            $toRefundItems,
            $currency,
            $reason
        );
    }

    /**
     * When performing a refund for order items should there be a match against the refunding items
     * and the order items retrieved by Mollie service.
     *
     * If the refunding item does not exists in mollie service an UnexpectedValueException is thrown
     *
     * @throws PHPUnit_Framework_Exception
     * @throws PartialRefundException
     * @throws UnexpectedValueException
     */
    public function testBuildLineItemsThrowUnexpectedValueExceptionBecauseRemoteItemNotFound()
    {
        /*
         * Stubs
         */
        $reason = uniqid();
        $currency = uniqid();

        $toRefundItemId = uniqid();
        $toRefundItem = $this->wooCommerceOrderItem(-1, mt_rand(-100, -1), 0);
        $toRefundRemoteItem = $this->orderLineItem(
            uniqid(),
            OrderLineStatus::CAN_BE_REFUNDED[0],
            [
                // The value here do the trick, infact the value to refund is different than the remote item price.
                'value' => mt_rand(101, 200),
                'currency' => uniqid(),
            ]
        );
        $toRefundRemoteItems = [uniqid() => $toRefundRemoteItem];
        $toRefundItems = [$toRefundItemId => $toRefundItem];

        /*
         * Sut
         */
        $refundLineItemsBuilder = new RefundLineItemsBuilder(
            $this->dataHelper
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            "Cannot refund {$toRefundItemId} item because it was not found in Mollie order. Aborting refund process. Try to do a refund by amount."
        );

        /*
         * Execute Test
         */
        $refundLineItemsBuilder->buildLineItems(
            $toRefundRemoteItems,
            $toRefundItems,
            $currency,
            $reason
        );
    }

    /**
     * @param $quantity
     * @param $total
     * @param $totalTax
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wooCommerceOrderItem($quantity, $total, $totalTax)
    {
        $orderItem = $this->createConfiguredMock(
            'WC_Order_Item',
            [
                'get_quantity' => $quantity,
                'get_total' => $total,
                'get_total_tax' => $totalTax,
            ]
        );

        return $orderItem;
    }

    /**
     * @param $id
     * @param $status
     * @param array $price
     * @param array|null $discountAmount
     * @return stdClass
     */
    private function orderLineItem($id, $status, array $price, array $discountAmount = null)
    {
        $orderLineItem = new stdClass();

        $orderLineItem->id = $id;
        $orderLineItem->status = $status;
        $orderLineItem->unitPrice = (object)$price;
        $orderLineItem->discountAmount = $discountAmount ? (object)$discountAmount : null;

        return $orderLineItem;
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
        $this->dataHelper = $this->dataHelper();
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function dataHelper()
    {
        $mock = $this
            ->getMockBuilder(Mollie_WC_Helper_Data::class)
            ->disableOriginalConstructor()
            ->setMethods(['formatCurrencyValue'])
            ->getMock();

        return $mock;
    }
}
