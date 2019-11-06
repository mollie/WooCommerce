<?php # -*- coding: utf-8 -*-

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use MollieTests\TestCase;
use Mollie_WC_Plugin as Testee;

/**
 * Class Mollie_WC_Plugin_Test
 */
class Mollie_WC_Plugin_Test extends TestCase
{
    /**
     * Test Disable Apple Pay Gateway
     *
     * @dataProvider allowingDataProvider
     */
    public function testMaybeDisableApplePayGateway($allowed, $expected)
    {
        /*
         * Setup stubs
         */
        $postData = Testee::APPLE_PAY_METHOD_ALLOWED_KEY . "={$allowed}";
        $gateways = [
            'Mollie_WC_Gateway_Applepay',
            new stdClass(),
        ];

        /*
         * Mocks
         */
        $wooCommerceSession = $this
            ->getMockBuilder('\\WC_Session')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'set'])
            ->getMock();

        /*
         * We test frontend
         */
        when('is_admin')->justReturn(false);

        /*
         * Expect to retrieve the WooCommerce Session
         * Wc Session is used to store the availability of Apple Pay payment method
         */
        expect('mollieWooCommerceSession')
            ->once()
            ->andReturn($wooCommerceSession);

        $wooCommerceSession
            ->expects($this->once())
            ->method('get')
            ->with(\Mollie_WC_Plugin::APPLE_PAY_METHOD_ALLOWED_KEY, false)
            ->willReturn(false);

        /*
         * Expect to have the request to remove the apple pay method
         */
        expect('filter_input')
            ->once()
            ->with(INPUT_POST, Testee::POST_DATA_KEY, FILTER_SANITIZE_STRING)
            ->andReturn($postData);

        $wooCommerceSession
            ->expects($allowed ? $this->once() : $this->never())
            ->method('set')
            ->with(\Mollie_WC_Plugin::APPLE_PAY_METHOD_ALLOWED_KEY, true);

        /*
         * Execute Test
         */
        $result = Testee::maybeDisableApplePayGateway($gateways);

        self::assertEquals($expected, in_array('Mollie_WC_Gateway_Applepay', $result, true));
    }

    public function allowingDataProvider()
    {
        return [
            [1, true],
            [0, false],
        ];
    }
}
