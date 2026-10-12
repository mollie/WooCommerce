<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;
/**
 * Middleware to handle Apple Pay token in the request.
 */
class ApplePayTokenMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    /**
     * Invoke the middleware.
     *
     * @param array $requestData The request data.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context The context of the request.
     * @param callable $next The next middleware to call.
     * @return array The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, $context, $next): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $applePayToken = wc_clean(wp_unslash($_POST["token"] ?? ''));
        if (!$applePayToken) {
            return $next($requestData, $order, $context);
        }
        $encodedApplePayToken = wp_json_encode($applePayToken);
        if ($context === 'order') {
            $requestData['payment']['applePayPaymentToken'] = $encodedApplePayToken;
        } elseif ($context === 'payment') {
            $requestData['applePayPaymentToken'] = $encodedApplePayToken;
        }
        return $next($requestData, $order, $context);
    }
}
