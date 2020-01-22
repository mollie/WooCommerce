<?php

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mollie\Api\Resources\Order;
use Mollie\WooCommerceTests\TestCase;

use Mollie_WC_Helper_Data;
use Mollie_WC_Payment_Order;
use Mollie_WC_Payment_OrderItemsRefunder;
use WC_Order;

use function Brain\Monkey\Functions\when;


class Mollie_WC_Payment_Order_Test extends TestCase
{
    /* -----------------------------------------------------------------
       onWebhookCanceled Tests
       -------------------------------------------------------------- */

    /**
     * given onWebhookCanceled is called
     * when order has status of Completed|Refunded|Cancelled
     * then status does NOT change and we exit the method.
     *
     * @test
     */
    public function onWebHookCanceled_Returns_whenFinalStatus(){
        /*
        * Setup Stubs
        */
        $orderItemsRefunded = $this->buildTesteeMock(
            Mollie_WC_Payment_OrderItemsRefunder::class,
            [],
            []
        )->getMock();

        $payment = $this->buildTesteeMock(
            Order::class,
            [],
            []
        )->getMock();
        $order = $this->buildTesteeMock(
            WC_Order::class,
            [],
            []
        )->getMock();
        $dataHelper = $this->buildTesteeMock(
            Mollie_WC_Helper_Data::class,
            [],
            ['hasOrderStatus']
        )->getMock();
        $payment_method_title = 'creditcard';

        /*
        * Setup Testee
        */
        $data = 'order';
        $testee = new Mollie_WC_Payment_Order($orderItemsRefunded, $data);

        /*
         * Expectations
         */
        when('wooCommerceOrderId')
            ->justReturn(89);
        when('debug')
            ->justReturn(true);
        when('getDataHelper')
            ->justReturn($dataHelper);
        $dataHelper
            ->expects($this->exactly(3))
            ->method('hasOrderStatus')
            ->willReturn(true);

        /*
         * Execute test
         */
        $testee->onWebhookCanceled($order, $payment, $payment_method_title);

    }


}
