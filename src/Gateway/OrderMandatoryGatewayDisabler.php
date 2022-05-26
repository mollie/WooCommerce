<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

class OrderMandatoryGatewayDisabler
{
    /**
     * @var bool
     */
    protected $isSettingsOrderApi;

    /**
     * OrderMandatoryGatewayDisabler constructor.
     */
    public function __construct(bool $isSettingsOrderApi)
    {
        $this->isSettingsOrderApi = $isSettingsOrderApi;
    }

    /**
     * @param array $gateways
     * @return array
     */
    public function processGateways(array $gateways): array
    {
        $isWcApiRequest = (bool)filter_input(
            INPUT_GET,
            'wc-api',
            FILTER_SANITIZE_STRING
        );
        if (
            ($isWcApiRequest
                || !doing_action('woocommerce_payment_gateways')
                || !wp_doing_ajax()
                || is_admin())
            && !has_block('woocommerce/checkout')
        ) {
            return $gateways;
        }
        if ($this->isSettingsOrderApi) {
            return $gateways;
        }
        foreach ($gateways as $key => $gateway) {
            $isMollieGateway = $gateway instanceof MolliePaymentGateway;

            if (!$isMollieGateway) {
                continue;
            }
            $isOrderMandatory = $gateway->paymentMethod->getProperty('orderMandatory');
            if ($isOrderMandatory) {
                unset($gateways[$key]);
            }
        }

        return $gateways;
    }
}
