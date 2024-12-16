<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Decorator;

use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use WC_Order;

class ApplePayTokenDecorator implements RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $applePayToken = wc_clean(wp_unslash($_POST["token"] ?? ''));
        if ($applePayToken && isset($requestData['payment'])) {
            $encodedApplePayToken = wp_json_encode($applePayToken);
            $requestData['payment']['applePayPaymentToken'] = $encodedApplePayToken;
        }
        return $requestData;
    }
}
