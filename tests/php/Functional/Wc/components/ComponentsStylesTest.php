<?php

namespace Mollie\WooCommerceTests\Functional\WC\Components;

use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Components_Styles;
use Mollie_WC_Settings_Components;
use PHPUnit_Framework_MockObject_MockObject;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

class ComponentsStylesTest extends TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mollie_WC_Settings_Components
     */
    private $mollieComponentsSettings;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|WC_Payment_Gateways
     */
    private $paymentGateways;

    public function testForAvailableGateways()
    {
        /*
         * Stubs
         */
        $styles = [uniqid()];
        $gatewayId = uniqid();
        $availablePaymentGateway = $this->mockEnabledMollieComponentsGateway(
            "mollie_wc_gateway_{$gatewayId}"
        );

        /*
         * Sut
         */
        $mollieComponentsStyles = new Mollie_WC_Components_Styles(
            $this->mollieComponentsSettings,
            $this->paymentGateways
        );

        /*
         * Expect to retrieve the information from the gateway instances
         */
        $this->paymentGateways
            ->expects($this->once())
            ->method('get_available_payment_gateways')
            ->willReturn([$availablePaymentGateway]);

        /*
         * Expect to retrieve the mollie components styles
         */
        $this->mollieComponentsSettings
            ->expects($this->once())
            ->method('styles')
            ->willReturn($styles);

        /*
         * Execute Test
         */
        $result = $mollieComponentsStyles->forAvailableGateways();

        self::assertEquals(
            [
                $gatewayId => [
                    'styles' => $styles,
                ],
            ],
            $result
        );
    }

    public function testForAvailableGatewaysReturnEmptyListBecauseGatewayIsDisabled()
    {
        /*
         * Stubs
         */
        $availablePaymentGateway = $this->mockDisabledGateway();

        /*
         * Sut
         */
        $mollieComponentsStyles = new Mollie_WC_Components_Styles(
            $this->mollieComponentsSettings,
            $this->paymentGateways
        );

        /*
         * Expect to retrieve the information from the gateway instances
         */
        $this->paymentGateways
            ->expects($this->once())
            ->method('get_available_payment_gateways')
            ->willReturn([$availablePaymentGateway]);

        /*
         * Execute Test
         */
        $result = $mollieComponentsStyles->forAvailableGateways();

        self::assertEquals([], $result);
    }

    public function testForAvailableGatewaysAreEmptyBecauseNoGatewaysHaveMollieComponentsEnabled()
    {
        /*
         * Stubs
         */
        $gatewayId = uniqid();
        $availablePaymentGateway = $this->mockDisabledMollieComponentsGateway(
            "mollie_wc_gateway_{$gatewayId}"
        );

        /*
         * Sut
         */
        $mollieComponentsStyles = new Mollie_WC_Components_Styles(
            $this->mollieComponentsSettings,
            $this->paymentGateways
        );

        /*
         * Expect to retrieve the information from the gateway instances
         */
        $this->paymentGateways
            ->expects($this->once())
            ->method('get_available_payment_gateways')
            ->willReturn([$availablePaymentGateway]);

        /*
         * Execute Test
         */
        $result = $mollieComponentsStyles->forAvailableGateways();

        self::assertEquals([], $result);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->mollieComponentsSettings = $this->mockMollieComponentsSettings();
        $this->paymentGateways = $this->mockPaymentGateways();
    }

    private function mockMollieComponentsSettings()
    {
        $mock = $this
            ->getMockBuilder(Mollie_WC_Settings_Components::class)
            ->disableOriginalConstructor()
            ->setMethods(['styles'])
            ->getMock();

        return $mock;
    }

    private function mockPaymentGateways()
    {
        $mock = $this
            ->getMockBuilder(WC_Payment_Gateways::class)
            ->disableOriginalConstructor()
            ->setMethods(['get_available_payment_gateways'])
            ->getMock();

        return $mock;
    }

    private function mockEnabledMollieComponentsGateway($gatewayId)
    {
        $mock = $this->getMockBuilder(WC_Payment_Gateway::class)->getMock();
        $mock->enabled = true;
        $mock->settings = ['mollie_components_enabled' => 'yes'];
        $mock->id = $gatewayId;

        return $mock;
    }

    private function mockDisabledGateway()
    {
        $mock = $this->getMockBuilder(WC_Payment_Gateway::class)->getMock();
        $mock->enabled = false;

        return $mock;
    }

    private function mockDisabledMollieComponentsGateway($gatewayId)
    {
        $mock = $this->getMockBuilder(WC_Payment_Gateway::class)->getMock();
        $mock->enabled = true;
        $mock->settings = ['mollie_components_enabled' => 'no'];
        $mock->id = $gatewayId;

        return $mock;
    }
}
