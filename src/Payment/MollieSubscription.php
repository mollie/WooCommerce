<?php

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Types\SequenceType;
use Mollie\WooCommerce\Payment\Request\Middleware\MiddlewareHandler;
use Mollie\WooCommerce\Payment\Request\Middleware\PaymentDescriptionMiddleware;
use Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler;
use Mollie\Psr\Log\LoggerInterface as Logger;
class MollieSubscription extends \Mollie\WooCommerce\Payment\MollieObject
{
    protected $pluginId;
    /**
     * @var mixed
     */
    private AbstractPaymentMethod $paymentMethod;
    protected MiddlewareHandler $middleware;
    /**
     * Molliesubscription constructor.
     *
     */
    public function __construct($pluginId, Api $apiHelper, $settingsHelper, $dataHelper, Logger $logger, AbstractPaymentMethod $paymentMethod, $middlewareHandler)
    {
        $this->pluginId = $pluginId;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->paymentMethod = $paymentMethod;
        $this->middleware = $middlewareHandler;
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
        if (!$gateway || !mollieWooCommerceIsMollieGateway($gateway->id)) {
            return ['result' => 'failure'];
        }
        $gatewayId = $gateway->id;
        $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
        $optionName = $this->pluginId . '_api_payment_description';
        $option = get_option($optionName);
        $paymentDescription = $this->getRecurringPaymentDescription($order, $option, $initialPaymentUsedOrderAPI);
        $requestData = array_filter(['amount' => ['currency' => $this->dataHelper->getOrderCurrency($order), 'value' => $this->dataHelper->formatCurrencyValue($order->get_total(), $this->dataHelper->getOrderCurrency($order))], 'description' => $paymentDescription, 'method' => $methodId, 'locale' => $paymentLocale, 'metadata' => ['order_id' => $order->get_id()], 'sequenceType' => SequenceType::SEQUENCETYPE_RECURRING, 'customerId' => $customerId]);
        $context = 'payment';
        return $this->middleware->handle($requestData, $order, $context);
    }
    protected function getRecurringPaymentDescription($order, $option, $initialPaymentUsedOrderAPI)
    {
        $description = !$option ? '' : trim($option);
        // Also use default when Order API was used on initial payment to match payment descriptions.
        if (!$description || $initialPaymentUsedOrderAPI) {
            $description = sprintf(
                /* translators: Placeholder 1: order number */
                _x('Order %1$s', 'Default payment description for subscription recurring payments', 'mollie-payments-for-woocommerce'),
                $order->get_order_number()
            );
            return $description;
        }
        $middleware = new PaymentDescriptionMiddleware($this->dataHelper);
        $requestData = [];
        $context = 'payment';
        $result = $middleware->__invoke($requestData, $order, $context, static function ($requestData) {
            return $requestData;
        });
        return $result['description'];
    }
    /**
     * Validate in the checkout if the gateway is available for subscriptions
     *
     * @param bool $status
     * @param MollieSubscriptionGatewayHandler $deprecatedSubscriptionHelper
     * @return bool
     */
    public function isAvailableForSubscriptions(bool $status, MollieSubscriptionGatewayHandler $deprecatedSubscriptionHelper, $orderTotal, $gateway): bool
    {
        $subscriptionPluginActive = class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin');
        if (!$subscriptionPluginActive) {
            return $status;
        }
        $currency = $deprecatedSubscriptionHelper->getCurrencyFromOrder();
        $billingCountry = $deprecatedSubscriptionHelper->getBillingCountry();
        $paymentLocale = $deprecatedSubscriptionHelper->dataService()->getPaymentLocale();
        // Check recurring totals against recurring payment methods for future renewal payments
        $recurringTotal = $deprecatedSubscriptionHelper->get_recurring_total();
        // See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
        $acceptManualRenewals = 'yes' === get_option(\WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals', 'no');
        $supportsSubscriptions = $gateway->supports('subscriptions');
        if ($acceptManualRenewals === \true || !$supportsSubscriptions || empty($recurringTotal)) {
            return $status;
        }
        foreach ($recurringTotal as $recurring_total) {
            // First check recurring payment methods CC and SDD
            $filters = $this->buildFilters($currency, $recurring_total, $billingCountry, SequenceType::SEQUENCETYPE_RECURRING, $paymentLocale);
            $status = $deprecatedSubscriptionHelper->isAvailableMethodInCheckout($filters);
        }
        // Check available first payment methods with today's order total, but ignore SSD gateway (not shown in checkout)
        if ($deprecatedSubscriptionHelper->paymentMethod()->getProperty('id') === 'mollie_wc_gateway_directdebit') {
            return $status;
        }
        $filters = $this->buildFilters($currency, $orderTotal, $billingCountry, SequenceType::SEQUENCETYPE_FIRST, $paymentLocale);
        return $deprecatedSubscriptionHelper->isAvailableMethodInCheckout($filters);
    }
    /**
     * @param string $currency
     * @param $recurring_total
     * @param string $billingCountry
     * @param string $sequenceType
     * @param string $paymentLocale
     * @return array
     */
    protected function buildFilters(string $currency, $recurring_total, string $billingCountry, string $sequenceType, string $paymentLocale): array
    {
        $filters = ['amount' => ['currency' => $currency, 'value' => $this->dataHelper->formatCurrencyValue($recurring_total, $currency)], 'resource' => 'orders', 'billingCountry' => $billingCountry, 'sequenceType' => $sequenceType];
        $paymentLocale and $filters['locale'] = $paymentLocale;
        return $filters;
    }
}
