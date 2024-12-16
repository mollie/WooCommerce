<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Decorator;

use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use WC_Order;

class CardTokenDecorator implements RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order): array
    {
        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken && isset($requestData['payment'])) {
            $requestData['payment']['cardToken'] = $cardToken;
        }
        return $requestData;
    }
}
