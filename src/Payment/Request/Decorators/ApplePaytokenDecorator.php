<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use WC_Order;

class ApplePayTokenDecorator implements RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $applePayToken = wc_clean(wp_unslash($_POST["token"] ?? ''));
        if (!$applePayToken) {
            return $requestData;
        }
        $encodedApplePayToken = wp_json_encode($applePayToken);
        if($context === 'order') {
            $requestData['payment']['applePayToken'] = $encodedApplePayToken;
        } elseif ($context === 'payment') {
            $requestData['applePayToken'] = $encodedApplePayToken;
        }
        return $requestData;
    }
}
