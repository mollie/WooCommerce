<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;
/**
 * Middleware to handle Card Token in the request.
 */
class CardTokenMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
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
        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken && isset($requestData['payment']) && $context === 'order') {
            $requestData['payment']['cardToken'] = $cardToken;
        } elseif ($cardToken && $context === 'payment') {
            $requestData['cardToken'] = $cardToken;
        }
        return $next($requestData, $order, $context);
    }
}
