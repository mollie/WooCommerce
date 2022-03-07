<?php


namespace Mollie\WooCommerce\Payment;


use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\SDK\Api;

class MollieSubscription extends MollieObject
{
    protected $pluginId;

    /**
     * Molliesubscription constructor.
     *
     */
    public function __construct( $pluginId, Api $apiHelper, $settingsHelper, $dataHelper, $logger)
    {
        $this->pluginId = $pluginId;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
    }
    /**
     * @param $order
     * @param $customerId
     * @return array
     */
    public function getRecurringPaymentRequestData($order, $customerId, $paymentService)
    {
        $paymentLocale = $this->settingsHelper->getPaymentLocale();
        $gateway = wc_get_payment_gateway_by_order($order);

        if (! $gateway || ! ( $gateway instanceof MolliePaymentGateway )) {
            return  [ 'result' => 'failure' ];
        }
        $gatewayId = $gateway->id;
        
        // TODO: is this the correct way to check which API was used for the initial payment?
        $molliePaymentType = $paymentService->paymentTypeBasedOnGateway($gateway->paymentMethod);
        $molliePaymentType = $paymentService->paymentTypeBasedOnProducts($order, $molliePaymentType);
        $initialPaymentUsedOrderAPI = PaymentService::PAYMENT_METHOD_TYPE_ORDER === $molliePaymentType;
        $optionName = $this->pluginId . '_' . 'api_payment_description';
        $option = get_option($optionName);
        $paymentDescription = $this->getRecurringPaymentDescription($order, $option, $initialPaymentUsedOrderAPI);
        
        $selectedIssuer = $gateway->getSelectedIssuer();
        $returnUrl = $gateway->get_return_url($order);
        $returnUrl = $this->getReturnUrl($order, $returnUrl);
        $webhookUrl = $this->getWebhookUrl($order, $gatewayId);

        return array_filter([
                                'amount' =>  [
                                    'currency' => $this->dataHelper->getOrderCurrency($order),
                                    'value' => $this->dataHelper->formatCurrencyValue(
                                        $order->get_total(),
                                        $this->dataHelper->getOrderCurrency($order)
                                    ),
                                ],
                                'description' => $paymentDescription,
                                'redirectUrl' => $returnUrl,
                                'webhookUrl' => $webhookUrl,
                                'method' => $gateway->paymentMethod->getProperty('id'),
                                'issuer' => $selectedIssuer,
                                'locale' => $paymentLocale,
                                'metadata' =>  [
                                    'order_id' => $order->get_id(),
                                ],
                                'sequenceType' => 'recurring',
                                'customerId' => $customerId,
                            ]);
    }

    protected function getRecurringPaymentDescription($order, $option, $initialPaymentUsedOrderAPI)
    {
        $description = empty( $option ) ? '': trim($option);

        // Also use default when Order API was used on initial payment to match payment descriptions.
        if ( empty( $description ) || $initialPaymentUsedOrderAPI ) {
            $description = sprintf(
                /* translators: Placeholder 1: order number */
                _x(
                    'Order %1$s',
                    'Default payment description for subscription recurring payments',
                    'mollie-payments-for-woocommerce'
                ),
                $order->get_order_number()
            );
        }

        switch ($description) {
            // Support for old deprecated options.
            // TODO: remove when deprecated
            case '{orderNumber}':
                $description = sprintf(
                    /* translators: Placeholder 1: order number */
                    _x(
                        'Order %1$s',
                        'Payment description for {orderNumber}',
                        'mollie-payments-for-woocommerce'
                    ),
                    $order->get_order_number()
                );
                break;
            case '{storeName}':
                $description = sprintf(
                    /* translators: Placeholder 1: store name */
                    _x(
                        'StoreName %1$s',
                        'Payment description for {storeName}',
                        'mollie-payments-for-woocommerce'
                    ),
                    get_bloginfo('name')
                );
                break;
            case '{customer.firstname}':
                $description = sprintf(
                    /* translators: Placeholder 1: customer first name */
                    _x(
                        'Customer Firstname %1$s',
                        'Payment description for {customer.firstname}',
                        'mollie-payments-for-woocommerce'
                    ),
                    $order->get_billing_first_name()
                );
                break;
            case '{customer.lastname}':
                $description = sprintf(
                    /* translators: Placeholder 1: customer last name */
                    _x(
                        'Customer Lastname %1$s',
                        'Payment description for {customer.lastname}',
                        'mollie-payments-for-woocommerce'
                    ),
                    $order->get_billing_last_name()
                );
                break;
            case '{customer.company}':
                $description = sprintf(
                    /* translators: Placeholder 1: customer company */
                    _x(
                        'Customer Company %1$s',
                        'Payment description for {customer.company}',
                        'mollie-payments-for-woocommerce'
                    ),
                    $order->get_billing_company()
                );
                break;
            // Support for custom string with interpolation.
            default:
                // Replace available description tags.
                $replacement_tags = [
                    '{orderNumber}' => $order->get_order_number(),
                    '{storeName}' => get_bloginfo('name'),
                    '{customer.firstname}' => $order->get_billing_first_name(),
                    '{customer.lastname}' => $order->get_billing_last_name(),
                    '{customer.company}' => $order->get_billing_company(),
                ];
                foreach ( $replacement_tags as $tag => $replacement ) {
                    $description = str_replace( $tag, $replacement, $description );
                }
                break;
        }

        // Fall back on default if description turns out empty.
        $description = empty( $description ) ? __( 'Order', 'woocommerce' ) . ' ' . $order->get_order_number(): $description;
        return $description;
    }
}
