<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;

class SelectedIssuerMiddleware implements RequestMiddlewareInterface
{
    private $pluginId;

    public function __construct($pluginId)
    {
        $this->pluginId = $pluginId;
    }

    public function __invoke(array $requestData, WC_Order $order, $context = null, $next): array
    {

        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway) {

            return $next($requestData, $order, $context);
        }

        $gatewayId = $gateway->id;
        $selectedIssuer = $this->getSelectedIssuer($gatewayId);
        if (empty($selectedIssuer)) {
            return $next($requestData, $order, $context);
        }
        if ($context === 'order') {
            $requestData['payment']['issuer'] = $selectedIssuer;
        } elseif ($context === 'payment') {
            $requestData['issuer'] = $selectedIssuer;
        }

        return $next($requestData, $order, $context);
    }

    private function getSelectedIssuer(string $gatewayId): string
    {
        $issuer_id = $this->pluginId . '_issuer_' . $gatewayId;
        //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $postedIssuer = wc_clean(wp_unslash($_POST[$issuer_id] ?? ''));
        return !empty($postedIssuer) ? $postedIssuer : '';
    }
}
