<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;

class PaymentDescriptionMiddleware implements RequestMiddlewareInterface
{
    private $dataHelper;

    public function __construct($dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    public function __invoke(array $requestData, WC_Order $order, string $context = null, $next): array
    {
        $optionName = $this->dataHelper->getPluginId() . '_' . 'api_payment_description';
        $option = get_option($optionName);
        $paymentDescription = $this->getPaymentDescription($order, $option);

        $requestData['description'] = $paymentDescription;
        return $next($requestData, $order, $context);
    }

    private function getPaymentDescription(WC_Order $order, $option): string
    {
        $description = !$option ? '' : trim($option);
        $description = !$description ? '{orderNumber}' : $description;

        switch ($description) {
            // Support for old deprecated options.
            // TODO: remove when deprecated
            case '{orderNumber}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'Order {orderNumber}',
                        'Payment description for {orderNumber}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{storeName}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'StoreName {storeName}',
                        'Payment description for {storeName}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.firstname}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'Customer Firstname {customer.firstname}',
                        'Payment description for {customer.firstname}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.lastname}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'Customer Lastname {customer.lastname}',
                        'Payment description for {customer.lastname}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            case '{customer.company}':
                $description =
                    /* translators: do not translate between {} */
                    _x(
                        'Customer Company {customer.company}',
                        'Payment description for {customer.company}',
                        'mollie-payments-for-woocommerce'
                    );
                $description = $this->replaceTagsDescription($order, $description);
                break;
            // Support for custom string with interpolation.
            default:
                // Replace available description tags.
                $description = $this->replaceTagsDescription($order, $description);
                break;
        }

        // Fall back on default if description turns out empty.
        return !$description ? __('Order', 'woocommerce') . ' ' . $order->get_order_number() : $description;
    }

    private function replaceTagsDescription(WC_Order $order, string $description): string
    {
        $replacement_tags = [
            '{orderNumber}' => $order->get_order_number(),
            '{storeName}' => get_bloginfo('name'),
            '{customer.firstname}' => $order->get_billing_first_name(),
            '{customer.lastname}' => $order->get_billing_last_name(),
            '{customer.company}' => $order->get_billing_company(),
        ];
        foreach ($replacement_tags as $tag => $replacement) {
            $description = str_replace($tag, $replacement, $description);
        }
        return $description;
    }
}
