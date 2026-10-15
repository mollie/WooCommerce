<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Inpsyde\PaymentGateway\PaymentGateway;
use Inpsyde\PaymentGateway\PaymentProcessorInterface;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressAssets;
use Mollie\WooCommerce\ExpressComponent\Rules\SurfaceOwnership;
use WC_Order;

/**
 * The Express rules for the Expresscheckout payment method. Blocks lists, saves and validates
 * payment methods only as available gateways, so the Express Component needs one.
 *
 * Offered only where Express owns the checkout, on the block checkout page and in Store API
 * requests: never on the classic checkout or the pay-for-order page. The wallet buttons pay, through
 * their own route; a checkout placed with this method is refused.
 */
class ExpressCheckoutGate implements PaymentProcessorInterface
{
    private const SURFACE = 'checkout';

    private ?bool $ownsCheckout = null;

    public function __construct(private ExpressFactsBuilder $facts)
    {
    }

    /**
     * Not in wp-admin or the admin's REST API, so it is not offered as a gateway to configure.
     */
    public static function belongsToRequest(): bool
    {
        if (is_admin()) {
            return false;
        }

        return !WC()->is_rest_api_request() || WC()->is_store_api_request();
    }

    public function isAvailable(): bool
    {
        if (!WC()->is_store_api_request() && !ExpressAssets::isBlockCheckoutPage()) {
            return false;
        }
        if ($this->ownsCheckout === null) {
            // Options only, before the check that asks Mollie for its active methods.
            $this->ownsCheckout = $this->facts->anyWalletTurnedOn()
                && SurfaceOwnership::owns($this->facts->settings(), $this->facts->shopFacts(), self::SURFACE);
        }

        return $this->ownsCheckout;
    }

    public function processPayment(WC_Order $order, PaymentGateway $gateway): array
    {
        wc_add_notice(
            __('Please pay with one of the express checkout buttons.', 'mollie-payments-for-woocommerce'),
            'error'
        );

        return ['result' => 'failure'];
    }
}
