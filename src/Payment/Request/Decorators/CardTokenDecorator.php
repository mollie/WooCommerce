<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Decorator;

use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use WC_Order;

class CardTokenDecorator implements RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order, $context): array
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
