<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerce\Tests\Unit;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use Mollie\WooCommerce\Tests\TestCase;
use Mollie_WC_Plugin as Testee;
use stdClass;

/**
 * Class Mollie_WC_PluginTest
 */
class Mollie_WC_PluginTest extends TestCase
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
        $postData = Testee::POST_APPLE_PAY_METHOD_ALLOWED_KEY . "={$allowed}";
        $gateways = [
            'Mollie_WC_Gateway_Applepay',
            new stdClass(),
        ];

        /*
         * We test frontend
         */
        when('is_admin')->justReturn(false);

        /*
         * Expect to have the request to remove the apple pay method
         */
        expect('filter_input')
            ->once()
            ->with(INPUT_POST, Testee::POST_DATA_KEY, FILTER_SANITIZE_STRING)
            ->andReturn($postData);

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
            [0, false]
        ];
    }
}
