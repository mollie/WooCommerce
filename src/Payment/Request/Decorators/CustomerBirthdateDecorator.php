<?php

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use WC_Order;

class CustomerBirthdateDecorator implements RequestDecoratorInterface
{
    private array $paymentMethods;

    public function __construct(array $paymentMethods)
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function decorate(array $requestData, WC_Order $order): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway || !isset($gateway->id)) {
            return $requestData;
        }
        if (strpos($gateway->id, 'mollie_wc_gateway_') === false) {
            return $requestData;
        }
        $paymentMethodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentMethod = $this->paymentMethods[$paymentMethodId];
        $additionalFields = $paymentMethod->getProperty('additionalFields');
        $methodId = $additionalFields && in_array('birthdate', $additionalFields, true);
        if ($methodId) {
            $optionName = 'billing_birthdate_' . $paymentMethod->getProperty('id');
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $fieldPosted = wc_clean(wp_unslash($_POST[$optionName] ?? ''));
            if ($fieldPosted === '' || !is_string($fieldPosted)) {
                return $requestData;
            }

            $order->update_meta_data($optionName, $fieldPosted);
            $order->save();
            $format = "Y-m-d";
            $requestData['consumerDateOfBirth'] = gmdate($format, (int) strtotime($fieldPosted));
        }
        return $requestData;
    }
}
