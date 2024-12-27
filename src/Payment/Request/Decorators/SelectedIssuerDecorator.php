<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use WC_Order;

class SelectedIssuerDecorator implements RequestDecoratorInterface
{
    private $pluginId;

    public function __construct($pluginId)
    {
        $this->pluginId = $pluginId;
    }

    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway) {
            return $requestData;
        }

        $gatewayId = $gateway->id;
        $selectedIssuer = $this->getSelectedIssuer($gatewayId);
        if (empty($selectedIssuer)) {
            return $requestData;
        }
        if ($context === 'order') {
            $requestData['payment']['issuer'] = $selectedIssuer;
        } elseif ($context === 'payment') {
            $requestData['issuer'] = $selectedIssuer;
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
