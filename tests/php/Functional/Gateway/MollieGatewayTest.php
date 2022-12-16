<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Gateway;

use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;


/**
 * Class Mollie_WC_Plugin_Test
 */
class MollieGatewayTest extends TestCase
{
    /**
     * WHEN gateway setting 'enabled' !== 'yes'
     * THEN is_available returns false
     * @test
     */
    public function gatewayNOTEnabledIsNOTAvailable()
    {
        $testee = $this->buildTestee(['enabled'=>'no']);

        $expectedResult = false;
        $result = $testee->is_available();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * WHEN gateway setting 'enabled' !== 'yes'
     * THEN is_available returns true
     * @test
     */
    public function gatewayEnabledIsAvailable()
    {
        $testee = $this->buildTestee(['enabled'=>'yes']);
        $total = 10.00;
        $WC = $this->woocommerceMocks->wooCommerce(10.00, 0, $total, 0);
        expect('WC')->andReturn($WC);
        $testee->expects($this->atLeast(2))->method('get_order_total')->willReturn($total);
        expect('get_woocommerce_currency')->andReturn('EUR');
        expect('get_transient')->andReturn([['id'=>'ideal']]);
        expect('wc_get_base_location')->andReturn(['country'=>'ES']);

        $expectedResult = true;
        $result = $testee->is_available();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * WHEN gateway setting 'enabled' !== 'yes'
     * AND the customer has no country set
     * THEN we fallback to the shop country and is_available returns true
     * @test
     */
    public function gatewayAvailableWhenNoCountrySelected()
    {
        $testee = $this->buildTestee(['enabled'=>'yes']);
        $total = 10.00;
        $WC = $this->woocommerceMocks->wooCommerce(10.00, 0, $total, 0, '');
        expect('WC')->andReturn($WC);
        $testee->expects($this->atLeast(2))->method('get_order_total')->willReturn($total);
        expect('get_woocommerce_currency')->andReturn('EUR');
        expect('get_transient')->andReturn([['id'=>'ideal']]);
        expect('wc_get_base_location')->andReturn(['country'=>'ES']);

        $expectedResult = true;
        $result = $testee->is_available();
        $this->assertEquals($expectedResult, $result);
    }

    private function buildTestee(array $settings)
    {
        return $this->helperMocks->mollieGatewayBuilder('Ideal', false, false, $settings);
    }
}



