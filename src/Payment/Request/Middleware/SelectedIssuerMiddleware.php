<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;
/**
 * Class SelectedIssuerMiddleware
 *
 * Middleware to handle the selection of the payment issuer.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class SelectedIssuerMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    /**
     * @var string The plugin ID.
     */
    private $pluginId;
    /**
     * SelectedIssuerMiddleware constructor.
     *
     * @param string $pluginId The plugin ID.
     */
    public function __construct(string $pluginId)
    {
        $this->pluginId = $pluginId;
    }
    /**
     * Invoke the middleware.
     *
     * @param array<string, mixed> $requestData The request data to be modified.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context Additional context for the middleware.
     * @param callable $next The next middleware to be called.
     * @return array<string, mixed> The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, string $context, callable $next): array
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
    /**
     * Get the selected issuer.
     *
     * @param string $gatewayId The payment gateway ID.
     * @return string The selected issuer.
     */
    private function getSelectedIssuer(string $gatewayId): string
    {
        $issuer_id = $this->pluginId . '_issuer_' . $gatewayId;
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $postedIssuer = wc_clean(wp_unslash($_POST[$issuer_id] ?? ''));
        return !empty($postedIssuer) ? $postedIssuer : '';
    }
}
