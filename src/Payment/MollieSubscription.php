<?php

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Types\SequenceType;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGateway;

class MollieSubscription extends MollieObject
{
    protected $pluginId;

    /**
     * Molliesubscription constructor.
     *
     */
    public function __construct($pluginId, Api $apiHelper, $settingsHelper, $dataHelper, $logger)
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
                                'method' => $gateway->paymentMethod()->getProperty('id'),
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
        if (!$description || $initialPaymentUsedOrderAPI) {
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

    /**
     * Validate in the checkout if the gateway is available for subscriptions
     *
     * @param bool $status
     * @param MollieSubscriptionGateway $subscriptionGateway
     * @return bool
     */
    public function isAvailableForSubscriptions(bool $status, MollieSubscriptionGateway $subscriptionGateway, $orderTotal): bool
    {
        $subscriptionPluginActive = class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin');
        if (!$subscriptionPluginActive) {
            return $status;
        }
        $currency = $subscriptionGateway->getCurrencyFromOrder();
        $billingCountry = $subscriptionGateway->getBillingCountry();
        $paymentLocale = $subscriptionGateway->dataService()->getPaymentLocale();
        // Check recurring totals against recurring payment methods for future renewal payments
        $recurringTotal = $subscriptionGateway->get_recurring_total();
        // See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
        $acceptManualRenewals = 'yes' === get_option(
            \WC_Subscriptions_Admin::$option_prefix
                . '_accept_manual_renewals',
            'no'
        );
        $supportsSubscriptions = $subscriptionGateway->supports('subscriptions');
        if ($acceptManualRenewals === true || !$supportsSubscriptions || empty($recurringTotal)) {
            return $status;
        }
        foreach ($recurringTotal as $recurring_total) {
            // First check recurring payment methods CC and SDD
            $filters = $this->buildFilters(
                $currency,
                $recurring_total,
                $billingCountry,
                SequenceType::SEQUENCETYPE_RECURRING,
                $paymentLocale
            );
            $status = $subscriptionGateway->isAvailableMethodInCheckout($filters);
        }

        // Check available first payment methods with today's order total, but ignore SSD gateway (not shown in checkout)
        if ($subscriptionGateway->paymentMethod()->getProperty('id') === 'mollie_wc_gateway_directdebit') {
            return $status;
        }
        $filters = $this->buildFilters(
            $currency,
            $orderTotal,
            $billingCountry,
            SequenceType::SEQUENCETYPE_FIRST,
            $paymentLocale
        );
        return $subscriptionGateway->isAvailableMethodInCheckout($filters);
    }

    /**
     * @param string $currency
     * @param $recurring_total
     * @param string $billingCountry
     * @param string $sequenceType
     * @param string $paymentLocale
     * @return array
     */
    protected function buildFilters(
        string $currency,
        $recurring_total,
        string $billingCountry,
        string $sequenceType,
        string $paymentLocale
    ): array {

        $filters = [
            'amount' => [
                'currency' => $currency,
                'value' => $this->dataHelper
                    ->formatCurrencyValue(
                        $recurring_total,
                        $currency
                    ),
            ],
            'resource' => 'orders',
            'billingCountry' => $billingCountry,
            'sequenceType' => $sequenceType,
        ];

        $paymentLocale and
        $filters['locale'] = $paymentLocale;
        return $filters;
    }
}
