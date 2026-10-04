<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\Api\Types\SequenceType;
use WC_Order;
/**
 * Middleware to handle Card Token in the request.
 */
class CaptureModeMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
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
        if ($context !== 'payment') {
            return $next($requestData, $order, $context);
        }
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway || !isset($gateway->id)) {
            return $requestData;
        }
        if (strpos($gateway->id, 'mollie_wc_gateway_') === \false) {
            return $requestData;
        }
        $requestData['captureMode'] = 'automatic';
        $paymentMethodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentMethod = $this->paymentMethods[$paymentMethodId];
        if ($paymentMethod->getProperty('paymentCaptureMode')) {
            $requestData['captureMode'] = $paymentMethod->getProperty('paymentCaptureMode');
        }
        if (!empty($requestData['sequenceType']) && $requestData['sequenceType'] !== SequenceType::SEQUENCETYPE_ONEOFF) {
            unset($requestData['captureMode']);
        }
        return $next($requestData, $order, $context);
    }
}
