<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;

class CardTokenMiddleware implements RequestMiddlewareInterface
{
    public function __invoke(array $requestData, WC_Order $order, $context = null, $next): array
    {
        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken && isset($requestData['payment']) && $context === 'order') {
            $requestData['payment']['cardToken'] = $cardToken;
        } elseif ($cardToken && isset($requestData['payment']) && $context === 'payment') {
            $requestData['cardToken'] = $cardToken;
        }
        return $next($requestData, $order, $context);
    }
}
