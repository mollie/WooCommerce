<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Components;

use Mollie\WooCommerce\Settings\SettingsComponents;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

class ComponentsStyles
{
    /**
     * @var SettingsComponents
     */
    protected $mollieComponentsSettings;

    /**
     * @var WC_Payment_Gateways
     */
    protected $paymentGateways;

    /**
     * ComponentsStyles constructor.
     * @param WC_Payment_Gateways $paymentGateways
     */
    public function __construct(
        SettingsComponents $mollieComponentsSettings,
        WC_Payment_Gateways $paymentGateways
    ) {

        $this->mollieComponentsSettings = $mollieComponentsSettings;
        $this->paymentGateways = $paymentGateways;
    }

    /**
     * Retrieve the mollie components styles for all of the available Gateways
     *
     * Gateways are enabled along with mollie components
     *
     * @return array
     */
    public function forAvailableGateways()
    {
        $availablePaymentGateways = $this->paymentGateways->get_available_payment_gateways();
        $gatewaysWithMollieComponentsEnabled = $this->gatewaysWithMollieComponentsEnabled(
            $availablePaymentGateways
        );

        if ($gatewaysWithMollieComponentsEnabled === []) {
            return [];
        }

        return $this->mollieComponentsStylesPerGateway(
            $this->mollieComponentsSettings->styles(),
            $gatewaysWithMollieComponentsEnabled
        );
    }

    /**
     * Retrieve the WooCommerce Gateways Which have the Mollie Components enabled
     *
     * @return array
     */
    protected function gatewaysWithMollieComponentsEnabled(array $gateways)
    {
        $gatewaysWithMollieComponentsEnabled = [];

        /** @var WC_Payment_Gateway $gateway */
        foreach ($gateways as $gateway) {
            $isGatewayEnabled = mollieWooCommerceStringToBoolOption($gateway->enabled);
            if ($isGatewayEnabled && $this->isMollieComponentsEnabledForGateway($gateway)) {
                $gatewaysWithMollieComponentsEnabled[] = $gateway;
            }
        }

        return $gatewaysWithMollieComponentsEnabled;
    }

    /**
     * Check if Mollie Components are enabled for the given gateway
     *
     * @param WC_Payment_Gateway $gateway
     * @return bool
     */
    protected function isMollieComponentsEnabledForGateway(WC_Payment_Gateway $gateway)
    {
        if (!isset($gateway->settings['mollie_components_enabled'])) {
            return false;
        }

        return mollieWooCommerceStringToBoolOption($gateway->settings['mollie_components_enabled']);
    }

    /**
     * Retrieve the mollie components styles associated to the given gateways
     *
     * @return array
     */
    protected function mollieComponentsStylesPerGateway(
        array $mollieComponentStyles,
        array $gateways
    ) {

        $gatewayNames = $this->gatewayNames($gateways);
        $mollieComponentsStylesGateways = array_combine(
            $gatewayNames,
            array_fill(
                0,
                count($gatewayNames),
                [
                    'styles' => $mollieComponentStyles,
                ]
            )
        );

        return $mollieComponentsStylesGateways ?: [];
    }

    /**
     * Extract the name of the gateways from the given gateways instances
     *
     * @return array
     */
    protected function gatewayNames(array $gateways)
    {
        $gatewayNames = [];

        /** @var WC_Payment_Gateway $gateway */
        foreach ($gateways as $gateway) {
            $gatewayNames[] = str_replace('mollie_wc_gateway_', '', $gateway->id);
        }

        return $gatewayNames;
    }
}
