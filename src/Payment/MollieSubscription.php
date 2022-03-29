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
    public function getRecurringPaymentRequestData($order, $customerId, $initialPaymentUsedOrderAPI)
    {
        $paymentLocale = $this->settingsHelper->getPaymentLocale();
        $gateway = wc_get_payment_gateway_by_order($order);

        if (! $gateway || ! ( $gateway instanceof MolliePaymentGateway )) {
            return  [ 'result' => 'failure' ];
        }
        $gatewayId = $gateway->id;
        $optionName = $this->pluginId . '_api_payment_description';
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
        $description = !$option ? '' : trim($option);

        // Also use default when Order API was used on initial payment to match payment descriptions.
        if ( !$description || $initialPaymentUsedOrderAPI ) {
            $description = sprintf(
                /* translators: Placeholder 1: order number */
                _x(
                    'Order %1$s',
                    'Default payment description for subscription recurring payments',
                    'mollie-payments-for-woocommerce'
                ),
                $order->get_order_number()
            );
            return $description;
        }
        return $this->getPaymentDescription($order, $option);
    }
}
