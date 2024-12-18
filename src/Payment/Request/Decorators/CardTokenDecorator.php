<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use WC_Order;

class CardTokenDecorator implements RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken && isset($requestData['payment']) && $context === 'order') {
            $requestData['payment']['cardToken'] = $cardToken;
        }elseif ($cardToken && isset($requestData['payment']) && $context === 'payment') {
            $requestData['cardToken'] = $cardToken;
        }
        return $requestData;
    }
}
