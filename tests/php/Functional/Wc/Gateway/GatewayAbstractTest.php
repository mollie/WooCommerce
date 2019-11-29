<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\WC\Gateway;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Payment_Object;
use PHPUnit_Framework_Exception;
use function Brain\Monkey\Functions\when;

/**
 * Class Mollie_WC_Gateway_AbstractTest
 * @package Mollie\WooCommerce\Tests\Unit
 */
class GatewayAbstractTest extends TestCase
{
    /**
     * Test paymentObject return a valid Mollie_WC_Payment_Object instance
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

        $result = \Mollie_WC_Plugin::getPaymentObject();

        self::assertInstanceOf(
            Mollie_WC_Payment_Object::class,
            $result
        );
    }
}
