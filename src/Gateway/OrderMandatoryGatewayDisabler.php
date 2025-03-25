<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Inpsyde\PaymentGateway\PaymentGateway;

class OrderMandatoryGatewayDisabler
{
    /**
     * @var bool
     */
    protected $isSettingsOrderApi;
    private array $paymentMethods;

    /**
     * OrderMandatoryGatewayDisabler constructor.
     */
    public function __construct(bool $isSettingsOrderApi, array $paymentMethods)
    {
        $this->isSettingsOrderApi = $isSettingsOrderApi;
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @param array $gateways
     * @return array
     */
    public function processGateways(array $gateways): array
    {
        $isWcApiRequest = isset($_GET['wc-api']) ? (bool)sanitize_text_field(wp_unslash($_GET['wc-api'])) : false;
        /*
         * There are 2 cases where we want to filter the gateway and it's when the checkout
         * page render the available payments methods.(classic and block)
         *
         * For any other case we want to be sure voucher gateway is included.
         */
        if (
            ($isWcApiRequest
                || !doing_action('woocommerce_payment_gateways')
                || (!wp_doing_ajax() && !is_wc_endpoint_url('order-pay'))
                || is_admin())
            && !has_block('woocommerce/checkout')
        ) {
            return $gateways;
        }
        if ($this->isSettingsOrderApi) {
            return $gateways;
        }
        $paymentMethods = $this->paymentMethods;
        return array_filter(
            $gateways,
            static function ($gateway) use ($paymentMethods) {
                if (! mollieWooCommerceIsMollieGateway($gateway)) {
                    return true;
                }
                $gatewayId = $gateway->id;
                $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
                $method = $paymentMethods[$methodId] ?? null;
                return !$method->getProperty('orderMandatory');
            }
        );
    }
}
