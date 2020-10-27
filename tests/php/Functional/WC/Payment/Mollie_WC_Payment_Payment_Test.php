<?php

namespace Mollie\WooCommerceTests\Functional\WC\Payment;

use Mollie\Api\Resources\Payment;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Helper_Data;
use Mollie_WC_Payment_Payment;
use WC_Order;
use function Brain\Monkey\Functions\when;
use Faker;

class Mollie_WC_Payment_Payment_Test extends TestCase
{
    /* -----------------------------------------------------------------
       onWebhookCanceled Tests
       -------------------------------------------------------------- */

    /**
     * given onWebhookCanceled is called
     * when payment has status of Completed|Refunded|Cancelled
     * then status does NOT change and we exit the method.
     *
     * @test
     */
    public function onWebHookCanceled_Returns_whenFinalStatus(){
        /*
        * Setup Stubs
        */
        $payment = $this->createMock(Payment::class);
        $order = $this->createMock(WC_Order::class);
        $dataHelper = $this->getMockBuilder(Mollie_WC_Helper_Data::class)
            ->setMethods(['getOrderStatus'])
            ->getMock();
        $dataHelper->method('getOrderStatus')
            ->willReturn('completed');
        $payment_method_title = 'creditcard';
        $faker = Faker\Factory::create();

        /*
        * Setup Testee
        */
        $data = 'order';
        $testee = new Mollie_WC_Payment_Payment($data);

        /*
         * Expectations
         */
        when('mollieWooCommerceOrderId')
            ->justReturn($faker->randomNumber(2));
        when('mollieWooCommerceDebug')
            ->justReturn($faker->word);
        when('mollieWooCommerceGetDataHelper')
            ->justReturn($dataHelper);

        /*
         * Execute test
         */
        $testee->onWebhookCanceled($order, $payment, $payment_method_title);
    }
}
