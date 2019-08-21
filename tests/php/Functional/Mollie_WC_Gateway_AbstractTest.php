<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerce\Tests\Functional;

use function Brain\Monkey\Functions\when;
use Mollie\WooCommerce\Tests\TestCase;
use Mollie_WC_Payment_Object;
use Mollie_WC_Plugin;

/**
 * Class Mollie_WC_Gateway_AbstractTest
 * @package Mollie\WooCommerce\Tests\Unit
 */
class Mollie_WC_Gateway_AbstractTest extends TestCase
{
    /**
     * Test paymentObject return a valid Mollie_WC_Payment_Object instance
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

        $result = Mollie_WC_Plugin::getPaymentObject();

        self::assertInstanceOf(
            Mollie_WC_Payment_Object::class,
            $result
        );
    }
}
