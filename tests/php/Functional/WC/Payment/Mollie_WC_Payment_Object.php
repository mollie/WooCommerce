<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\WC\Payment;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Payment_Object;
use PHPUnit_Framework_Exception;
use function Brain\Monkey\Functions\when;

class Mollie_WC_Payment_Object_Test extends TestCase
{
    /**
     * Test paymentObject return a valid Object instance
     * @throws PHPUnit_Framework_Exception
     */
    public function testPaymentObject()
    {
        /*
         * Setup Stubs
         */
        when('wc_get_base_location')
            ->justReturn(
                [
                    'country' => uniqid(),
                ]
            );

        $result = \Plugin::getPaymentObject();

        self::assertInstanceOf(
            Mollie_WC_Payment_Object::class,
            $result
        );
    }
}
