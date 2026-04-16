<?php

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;
use Mollie\WooCommerce\Shared\FieldConstants;
/**
 * Middleware to handle customer birthdate in the request.
 */
class CustomerBirthdateMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
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
        $birthdatePostedFieldName = $this->getBirthdatePostedFieldName($order);
        if (!$birthdatePostedFieldName || $birthdatePostedFieldName === '' || !is_string($birthdatePostedFieldName)) {
            return $next($requestData, $order, $context);
        }
        $format = "Y-m-d";
        //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $fieldPosted = wc_clean(wp_unslash($_POST[$birthdatePostedFieldName] ?? ''));
        $requestData['consumerDateOfBirth'] = gmdate($format, (int) strtotime($fieldPosted));
        return $next($requestData, $order, $context);
    }
    /**
     * Each payment method has a different birthdate field name or uses the default.
     *
     * @param WC_Order $order
     * @return string The phone posted field name for the given order.
     */
    private function getBirthdatePostedFieldName(WC_Order $order): string
    {
        $method = $order->get_payment_method();
        $cleanMethod = str_replace('mollie_wc_gateway_', '', $method);
        $constantName = strtoupper($cleanMethod) . '_BIRTHDATE';
        if (defined(FieldConstants::class . '::' . $constantName)) {
            return constant(FieldConstants::class . '::' . $constantName);
        }
        return '';
    }
}
