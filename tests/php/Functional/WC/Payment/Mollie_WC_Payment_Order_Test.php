<?php

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mollie\Api\Resources\Order;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Helper_Data;
use Mollie_WC_Payment_Order;
use Mollie_WC_Payment_OrderItemsRefunder;
use WC_Order;
use function Brain\Monkey\Functions\when;
use Faker;


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
        $orderItemsRefunded = $this->createMock(
                Mollie_WC_Payment_OrderItemsRefunder::class
            );
        $payment = $this->createMock(Order::class);
        $order = $this->createMock(WC_Order::class);
        $dataHelper = $this->createConfiguredMock(
            Mollie_WC_Helper_Data::class,
            ['getOrderStatus' => 'completed']
        );
        $faker = Faker\Factory::create();
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
            ->justReturn($faker->randomNumber(2));
        when('debug')
            ->justReturn($faker->word);
        when('getDataHelper')
            ->justReturn($dataHelper);

        /*
         * Execute test
         */
        $testee->onWebhookCanceled($order, $payment, $payment_method_title);
    }
}
