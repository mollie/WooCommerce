<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Decorator;

use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use WC_Order;

class SelectedIssuerDecorator implements RequestDecoratorInterface
{
    private $pluginId;

    public function __construct($pluginId)
    {
        $this->pluginId = $pluginId;
    }

    public function decorate(array $requestData, WC_Order $order): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if ($gateway) {
            $gatewayId = $gateway->id;
            $selectedIssuer = $this->getSelectedIssuer($gatewayId);
            $requestData['payment']['issuer'] = $selectedIssuer;
        }
        return $requestData;
    }

    private function getSelectedIssuer(string $gatewayId): string
    {
        $issuer_id = $this->pluginId . '_issuer_' . $gatewayId;
        //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $postedIssuer = wc_clean(wp_unslash($_POST[$issuer_id] ?? ''));
        return !empty($postedIssuer) ? $postedIssuer : '';
    }
}
