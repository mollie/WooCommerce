<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;
/**
 * Class PaymentDescriptionMiddleware
 *
 * Middleware to handle payment description in the request.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class PaymentDescriptionMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    /**
     * @var mixed The data helper instance.
     */
    private $dataHelper;
    /**
     * PaymentDescriptionMiddleware constructor.
     *
     * @param mixed $dataHelper The data helper instance.
     */
    public function __construct($dataHelper)
    {
        $this->dataHelper = $dataHelper;
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
    public function __invoke(array $requestData, WC_Order $order, string $context, $next): array
    {
        $optionName = $this->dataHelper->getPluginId() . '_' . 'api_payment_description';
        $option = get_option($optionName);
        $paymentDescription = $this->getPaymentDescription($order, $option);
        $requestData['description'] = $paymentDescription;
        return $next($requestData, $order, $context);
    }
    /**
     * Get the payment description.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param mixed $option The option value.
     * @return string The payment description.
     */
    private function getPaymentDescription(WC_Order $order, $option): string
    {
        $description = !$option ? '' : trim($option);
        $description = !$description ? '{orderNumber}' : $description;
        switch ($description) {
            case '{orderNumber}':
                $description = _x('Order {orderNumber}', 'Payment description for {orderNumber}', 'mollie-payments-for-woocommerce');
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{storeName}':
                $description = _x('StoreName {storeName}', 'Payment description for {storeName}', 'mollie-payments-for-woocommerce');
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.firstname}':
                $description = _x('Customer Firstname {customer.firstname}', 'Payment description for {customer.firstname}', 'mollie-payments-for-woocommerce');
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.lastname}':
                $description = _x('Customer Lastname {customer.lastname}', 'Payment description for {customer.lastname}', 'mollie-payments-for-woocommerce');
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.company}':
                $description = _x('Customer Company {customer.company}', 'Payment description for {customer.company}', 'mollie-payments-for-woocommerce');
                $description = $this->replaceTagsDescription($order, $description);
                break;
            default:
                $description = $this->replaceTagsDescription($order, $description);
                break;
        }
        return !$description ? __('Order', 'woocommerce') . ' ' . $order->get_order_number() : $description;
    }
    /**
     * Replace tags in the description with actual values.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param string $description The description with tags.
     * @return string The description with tags replaced.
     */
    private function replaceTagsDescription(WC_Order $order, string $description): string
    {
        $replacement_tags = ['{orderNumber}' => $order->get_order_number(), '{storeName}' => get_bloginfo('name'), '{customer.firstname}' => $order->get_billing_first_name(), '{customer.lastname}' => $order->get_billing_last_name(), '{customer.company}' => $order->get_billing_company()];
        foreach ($replacement_tags as $tag => $replacement) {
            $description = str_replace($tag, $replacement, $description);
        }
        return $description;
    }
}
