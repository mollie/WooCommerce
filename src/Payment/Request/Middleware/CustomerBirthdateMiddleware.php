<?php

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;

/**
 * Middleware to handle customer birthdate in the request.
 */
class CustomerBirthdateMiddleware implements RequestMiddlewareInterface
{
    /**
     * @var array The payment methods.
     */
    private array $paymentMethods;

    /**
     * Constructor.
     *
     * @param array $paymentMethods The payment methods.
     */
    public function __construct(array $paymentMethods)
    {
        $this->paymentMethods = $paymentMethods;
    }

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
            // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $fieldPosted = wc_clean(wp_unslash($_POST[$optionName] ?? ''));
            if ($fieldPosted === '' || !is_string($fieldPosted)) {
                return $requestData;
            }

            $order->update_meta_data($optionName, $fieldPosted);
            $order->save();
            $format = "Y-m-d";
            $requestData['consumerDateOfBirth'] = gmdate($format, (int) strtotime($fieldPosted));
        }
        return $next($requestData, $order, $context);
    }
}
