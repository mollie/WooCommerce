<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Subscription;

use Exception;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieSubscription;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\Request\Middleware\MiddlewareHandler;
use Mollie\WooCommerce\Payment\Request\Middleware\SelectedIssuerMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\UrlMiddleware;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\SDK\InvalidApiKey;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\Psr\Log\LoggerInterface as Logger;
use Mollie\WooCommerce\PaymentMethods\Constants;
use WC_Order;
class MollieSubscriptionGatewayHandler extends MolliePaymentGatewayHandler
{
    protected const PAYMENT_TEST_MODE = 'test';
    protected const METHODS_NEEDING_UPDATE = [
        'mollie_wc_gateway_bancontact',
        'mollie_wc_gateway_belfius',
        'mollie_wc_gateway_directdebit',
        'mollie_wc_gateway_eps',
        'mollie_wc_gateway_giropay',
        //stays for old subscriptions
        'mollie_wc_gateway_ideal',
        'mollie_wc_gateway_kbc',
        'mollie_wc_gateway_sofort',
        //stays for old subscriptions
        'mollie_wc_gateway_paybybank',
    ];
    protected const DIRECTDEBIT = Constants::DIRECTDEBIT;
    protected $apiHelper;
    protected $settingsHelper;
    /**
     * @var MollieSubscription
     */
    protected $subscriptionObject;
    /**
     * AbstractSubscription constructor.
     */
    public function __construct(PaymentMethodI $paymentMethod, OrderInstructionsManager $orderInstructionsProcessor, MollieOrderService $mollieOrderService, Data $dataService, Logger $logger, NoticeInterface $notice, HttpResponse $httpResponse, Settings $settingsHelper, MollieObject $mollieObject, PaymentFactory $paymentFactory, string $pluginId, Api $apiHelper)
    {
        parent::__construct($paymentMethod, $orderInstructionsProcessor, $mollieOrderService, $dataService, $logger, $notice, $httpResponse, $mollieObject, $paymentFactory, $pluginId);
        $this->apiHelper = $apiHelper;
        $middlewares = [new UrlMiddleware($pluginId, $logger), new SelectedIssuerMiddleware($pluginId)];
        $middlewareHandler = new MiddlewareHandler($middlewares);
        $this->subscriptionObject = new MollieSubscription($pluginId, $apiHelper, $settingsHelper, $dataService, $logger, $paymentMethod, $middlewareHandler);
    }
    public function addSubscriptionFilters($gateway)
    {
        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_scheduled_subscription_payment_' . $gateway->id, function ($renewal_total, WC_Order $renewal_order) use ($gateway) {
                $this->scheduled_subscription_payment($renewal_total, $renewal_order, $gateway);
            }, 10, 3);
            // A resubscribe order to record a customer resubscribing to an expired or cancelled subscription.
            add_action('wcs_resubscribe_order_created', [$this, 'delete_resubscribe_meta']);
            // After creating a renewal order to record a scheduled subscription payment with the same post meta, order items etc.
            add_filter('wcs_renewal_order_created', [$this, 'delete_renewal_meta']);
            add_action('woocommerce_subscription_failing_payment_method_updated_mollie', [$this, 'update_failing_payment_method'], 10, 2);
            add_filter('woocommerce_subscription_payment_meta', function ($payment_meta, $subscription) use ($gateway) {
                return $this->add_subscription_payment_meta($payment_meta, $subscription, $gateway);
            }, 10, 3);
            add_action('woocommerce_subscription_validate_payment_meta', function ($payment_method_id, $payment_meta) use ($gateway) {
                $this->validate_subscription_payment_meta($payment_method_id, $payment_meta, $gateway);
            }, 10, 2);
        }
    }
    /**
     * @param \WC_Order $renewal_order
     *
     * @return void
     */
    public function update_subscription_status_for_direct_debit($renewal_order)
    {
        // Make sure order is a renewal order with subscription
        if (!wcs_order_contains_renewal($renewal_order)) {
            return;
        }
        $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order);
        $subscription = $subscriptions && is_array($subscriptions) ? array_pop($subscriptions) : null;
        if (!$subscription) {
            return;
        }
        // Check that subscription status isn't already active
        if ($subscription->get_status() === 'active') {
            return;
        }
        // Check that payment method is SEPA Direct Debit or similar
        $methods_needing_update = self::METHODS_NEEDING_UPDATE;
        $current_method = $subscription->get_payment_method();
        if (in_array($current_method, $methods_needing_update, \true) === \false) {
            return;
        }
        // Check if WooCommerce Subscriptions Failed Recurring Payment Retry System is in-use, if it is, don't update subscription status
        if (class_exists('WCS_Retry_Manager') && \WCS_Retry_Manager::is_retry_enabled() && $subscription->get_date('payment_retry') > 0) {
            $this->logger->debug(__METHOD__ . ' - WooCommerce Subscriptions Failed Recurring Payment Retry System in use, not updating subscription status to Active!');
            return;
        }
        // Check that a new payment is made for renewal order
        $mollie_payment_id = $renewal_order->get_meta('_mollie_payment_id', \true);
        if ($mollie_payment_id === null) {
            return;
        }
        // Update subscription to Active
        try {
            $subscription->update_status('active');
        } catch (Exception $e) {
            // Already logged by WooCommerce Subscriptions
            $this->logger->debug('Could not update subscription ' . $subscription->get_id() . ' status:' . $e->getMessage());
        }
        // Add order note to subscription explaining the change
        $subscription->add_order_note(
            /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('Updated subscription from \'On hold\' to \'Active\' until payment fails, because a SEPA Direct Debit payment takes some time to process.', 'mollie-payments-for-woocommerce')
        );
    }
    /**
     * @param          $renewal_total
     * @param WC_Order $renewal_order
     *
     * @return array
     * @throws InvalidApiKey
     */
    public function scheduled_subscription_payment($renewal_total, WC_Order $renewal_order, $gateway)
    {
        if (!$renewal_order) {
            $this->logger->debug($this->id . ': Could not load renewal order or process renewal payment.');
            return ['result' => 'failure'];
        }
        $renewal_order_id = $renewal_order->get_id();
        // Allow developers to hook into the subscription renewal payment before it processed
        do_action($this->pluginId . '_before_renewal_payment_created', $renewal_order);
        $this->logger->debug($gateway->id . ': Try to create renewal payment for renewal order ' . $renewal_order_id);
        $initial_order_status = $this->paymentMethod->getInitialOrderStatus();
        // Overwrite plugin-wide
        $initial_order_status = apply_filters($this->pluginId . '_initial_order_status', $initial_order_status);
        // Overwrite gateway-wide
        $initial_order_status = apply_filters($this->pluginId . '_initial_order_status_' . $gateway->id, $initial_order_status);
        // Get Mollie customer ID
        $customer_id = $this->getOrderMollieCustomerId($renewal_order);
        $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order);
        $subscription = array_pop($subscriptions);
        $subscription_mollie_payment_id = $subscription->get_meta('_mollie_payment_id');
        $mandateId = $subscription->get_meta('_mollie_mandate_id');
        $subscriptionParentOrder = $subscription->get_parent();
        if (!empty($subscriptionParentOrder)) {
            if (empty($subscription_mollie_payment_id)) {
                $subscription_mollie_payment_id = $subscriptionParentOrder->get_meta('_mollie_payment_id');
                if ($subscription_mollie_payment_id) {
                    $subscription->add_meta_data('_mollie_payment_id', $subscription_mollie_payment_id);
                    $subscription->save();
                }
            }
            if (empty($mandateId)) {
                $mandateId = $subscriptionParentOrder->get_meta('_mollie_mandate_id');
                if ($mandateId) {
                    $subscription->add_meta_data('_mollie_mandate_id', $mandateId);
                    $subscription->save();
                }
            }
        }
        if (!empty($subscription_mollie_payment_id) && !empty($subscription)) {
            $customer_id = $this->restore_mollie_customer_id_and_mandate($customer_id, $subscription_mollie_payment_id, $subscription);
        }
        // Get all data for the renewal payment
        $initialPaymentUsedOrderAPI = $this->initialPaymentUsedOrderAPI($subscriptionParentOrder);
        $data = $this->subscriptionObject->getRecurringPaymentRequestData($renewal_order, $customer_id, $initialPaymentUsedOrderAPI);
        // Allow filtering the renewal payment data
        $data = apply_filters('woocommerce_' . $gateway->id . '_args', $data, $renewal_order);
        // Create a renewal payment
        try {
            do_action($this->pluginId . '_create_payment', $data, $renewal_order);
            $apiKey = $this->dataService->getApiKey();
            $mollieApiClient = $this->apiHelper->getApiClient($apiKey);
            $validMandate = \false;
            $renewalOrderMethod = $renewal_order->get_payment_method();
            $isRenewalMethodDirectDebit = in_array($renewalOrderMethod, self::METHODS_NEEDING_UPDATE, \true);
            $renewalOrderMethod = str_replace("mollie_wc_gateway_", "", $renewalOrderMethod);
            try {
                if (!empty($mandateId)) {
                    list($mandate, $data, $validMandate) = $this->usePreviousMandate($renewal_order_id, $customer_id, $mollieApiClient, $mandateId, $isRenewalMethodDirectDebit, $data, $validMandate, $gateway);
                }
                if (!$validMandate) {
                    list($validMandate, $data) = $this->useAnyValidMandate($renewal_order_id, $customer_id, $mollieApiClient, $validMandate, $data, $renewalOrderMethod, $gateway);
                }
            } catch (ApiException $e) {
                throw new ApiException(sprintf(
                    /* translators: Placeholder 1: customer id. */
                    __('The customer (%s) could not be used or found. ', 'mollie-payments-for-woocommerce') . $e->getMessage(),
                    $customer_id
                ));
            }
            // Check that there is at least one valid mandate
            try {
                if ($validMandate) {
                    $payment = $this->apiHelper->getApiClient($apiKey)->payments->create($data);
                    //check the payment method is the one in the order, if not we want this payment method in the order MOL-596
                    $paymentMethodUsed = 'mollie_wc_gateway_' . $payment->method;
                    if ($paymentMethodUsed !== $renewalOrderMethod) {
                        $renewal_order->set_payment_method($paymentMethodUsed);
                    }
                    $renewal_order->save();
                    //update the valid mandate for this order
                    if (property_exists($payment, 'mandateId') && $payment->mandateId !== null && $payment->mandateId !== $mandateId) {
                        $this->logger->debug("{$gateway->id}: updating to mandate {$payment->mandateId}");
                        $subscription->update_meta_data('_mollie_mandate_id', $payment->mandateId);
                        $subscription->save();
                        if ($subscriptionParentOrder) {
                            $subscriptionParentOrder->update_meta_data('_mollie_mandate_id', $payment->mandateId);
                            $subscriptionParentOrder->save();
                        }
                        $mandateId = $payment->mandateId;
                    }
                } else {
                    throw new ApiException(sprintf(
                        /* translators: Placeholder 1: customer id. */
                        __('The customer (%s) does not have a valid mandate.', 'mollie-payments-for-woocommerce-mandate-problem'),
                        $customer_id
                    ));
                }
            } catch (ApiException $e) {
                throw $e;
            }
            // Update first payment method to actual recurring payment method used for renewal order
            $this->updateFirstPaymentMethodToRecurringPaymentMethod($renewal_order, $renewal_order_id, $payment);
            // Log successful creation of payment
            $this->logger->debug($gateway->id . ': Renewal payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $renewal_order_id . ' payment json response: ' . wp_json_encode($payment));
            if (isset($payment->_links->changePaymentState->href) && $payment->mode === 'test') {
                $renewal_order->add_order_note('MOLLIE TEST MODE: URL to change payment state for renewal payment: <a href="' . $payment->_links->changePaymentState->href . '" target="_blank">' . $payment->_links->changePaymentState->href . '</a>');
            }
            // Unset & set active Mollie payment
            // Get correct Mollie Payment Object
            $payment_object = $this->paymentFactory->getPaymentObject($payment);
            $payment_object->unsetActiveMolliePayment($renewal_order_id);
            $payment_object->setActiveMolliePayment($renewal_order_id);
            // Set Mollie customer
            $this->dataService->setUserMollieCustomerIdAtSubscription($renewal_order_id, $payment_object->customerId());
            // Tell WooCommerce a new payment was created for the order/subscription
            do_action($this->pluginId . '_payment_created', $payment, $renewal_order);
            // Update order status and add order note
            $this->updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment);
            // Update status of subscriptions with payment method SEPA Direct Debit or similar
            $this->update_subscription_status_for_direct_debit($renewal_order);
            // Tell WooCommerce a new payment was created for the order/subscription
            do_action($this->pluginId . '_after_renewal_payment_created', $payment, $renewal_order);
            return ['result' => 'success'];
        } catch (ApiException $e) {
            $this->logger->debug("{$gateway->id} : Failed to create payment for order {$renewal_order_id}, with customer {$customer_id} and mandate {$mandateId}. New status failed. API error: {$e->getMessage()}");
            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(__('Could not create %s renewal payment.', 'mollie-payments-for-woocommerce'), $gateway->title);
            $message .= ' ' . $e->getMessage();
            $renewal_order->update_status(SharedDataDictionary::STATUS_FAILED, $message);
        }
        return ['result' => 'failure'];
    }
    public function isTestModeEnabledForRenewalOrder($order)
    {
        $result = \false;
        $subscriptions = [];
        if (wcs_order_contains_renewal($order->get_id())) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order->get_id());
        }
        foreach ($subscriptions as $subscription) {
            $paymentMode = $subscription->get_meta('_mollie_payment_mode', \true);
            // If subscription does not contain the mode, try getting it from the parent order
            if (empty($paymentMode)) {
                $parent_order = new \WC_Order($subscription->get_parent_id());
                $paymentMode = $parent_order->get_meta('_mollie_payment_mode', \true);
            }
            if ($paymentMode === self::PAYMENT_TEST_MODE) {
                $result = \true;
                break;
            }
        }
        return $result;
    }
    /**
     * @param WC_Order                            $renewal_order
     * @param                                     $renewal_order_id
     * @param \Mollie\Api\Resources\Payment        $payment
     *
     */
    public function updateFirstPaymentMethodToRecurringPaymentMethod($renewal_order, $renewal_order_id, $payment)
    {
        // Update first payment method to actual recurring payment method used for renewal order, this is
        // for subscriptions where the first order used methods like iDEAL as first payment and
        // later renewal orders switch to SEPA Direct Debit.
        $methods_needing_update = self::METHODS_NEEDING_UPDATE;
        if (($key = array_search('mollie_wc_gateway_' . Constants::DIRECTDEBIT, $methods_needing_update, \true)) !== \false) {
            unset($methods_needing_update[$key]);
        }
        $current_method = $renewal_order->get_payment_method();
        if (in_array($current_method, $methods_needing_update, \true) && $payment->method === self::DIRECTDEBIT) {
            try {
                $renewal_order->set_payment_method('mollie_wc_gateway_' . Constants::DIRECTDEBIT);
                $renewal_order->set_payment_method_title('SEPA Direct Debit');
                $renewal_order->save();
            } catch (\WC_Data_Exception $e) {
                $this->logger->debug('Updating payment method to SEPA Direct Debit failed for renewal order: ' . $renewal_order_id);
            }
        }
    }
    /**
     * @return mixed
     */
    protected function getCurrentLocale()
    {
        return apply_filters('wpml_current_language', get_locale());
    }
    /**
     * @param $order
     * @return mixed
     */
    public function getOrderMollieCustomerId($order)
    {
        return $order->get_meta('_mollie_customer_id', \true);
    }
    /**
     * @param $renewal_order
     * @param $initial_order_status
     * @param $payment
     */
    protected function updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment)
    {
        $this->mollieOrderService->updateOrderStatus($renewal_order, $initial_order_status, __('Awaiting payment confirmation.', 'mollie-payments-for-woocommerce') . "\n");
        $payment_method_title = $this->paymentMethod->getProperty('title');
        $renewal_order->add_order_note(sprintf(
            /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%1$s payment started (%2$s).', 'mollie-payments-for-woocommerce'),
            $payment_method_title,
            $payment->id . ($payment->mode === 'test' ? ' - ' . __('test mode', 'mollie-payments-for-woocommerce') : '')
        ));
    }
    /**
     * @param $resubscribe_order
     */
    public function delete_resubscribe_meta($resubscribe_order)
    {
        $this->delete_renewal_meta($resubscribe_order);
    }
    /**
     * @param \WC_Order|null $renewal_order
     * @return mixed
     */
    public function delete_renewal_meta($renewal_order)
    {
        if (!$renewal_order instanceof \WC_Order) {
            return $renewal_order;
        }
        $renewal_order->delete_meta_data('_mollie_payment_id');
        $renewal_order->delete_meta_data('_mollie_cancelled_payment_id');
        $renewal_order->save();
        return $renewal_order;
    }
    /**
     * @param $payment_meta
     * @param $subscription
     *
     * @return mixed
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function add_subscription_payment_meta($payment_meta, $subscription, $gateway)
    {
        assert($subscription instanceof \WC_Subscription);
        assert($gateway instanceof \WC_Payment_Gateway);
        if ($gateway->id !== $subscription->get_payment_method()) {
            return $payment_meta;
        }
        $mollie_payment_id = $subscription->get_meta('_mollie_payment_id', \true);
        $mollie_payment_mode = $subscription->get_meta('_mollie_payment_mode', \true);
        $mollie_customer_id = $subscription->get_meta('_mollie_customer_id', \true);
        $parent = $subscription->get_parent();
        if (empty($mollie_payment_id) && $parent) {
            $mollie_payment_id = $parent->get_meta('_mollie_payment_id', \true);
            $subscription->update_meta_data('_mollie_payment_id', $mollie_payment_id);
            $mollie_customer_id = $parent->get_meta('_mollie_customer_id', \true);
            $subscription->update_meta_data('_mollie_customer_id', $mollie_customer_id);
            $mollie_payment_mode = $parent->get_meta('_mollie_payment_mode', \true);
            $subscription->update_meta_data('_mollie_payment_mode', $mollie_payment_mode);
            $subscription->save();
        }
        $payment_meta[$gateway->id] = ['post_meta' => ['_mollie_payment_id' => ['value' => $mollie_payment_id, 'label' => 'Mollie Payment ID'], '_mollie_payment_mode' => ['value' => $mollie_payment_mode, 'label' => 'Mollie Payment Mode'], '_mollie_customer_id' => ['value' => $mollie_customer_id, 'label' => 'Mollie Customer ID']]];
        return $payment_meta;
    }
    /**
     * @param $payment_method_id
     * @param $payment_meta
     * @throws Exception
     */
    public function validate_subscription_payment_meta($payment_method_id, $payment_meta, $gateway)
    {
        if ($gateway->id === $payment_method_id) {
            // Check that a Mollie Customer ID is entered
            if (!isset($payment_meta['post_meta']['_mollie_customer_id']['value']) || empty($payment_meta['post_meta']['_mollie_customer_id']['value'])) {
                throw new Exception('A "_mollie_customer_id" value is required.');
            }
        }
    }
    /**
     * @param \WC_Subscription $subscription
     * @param \WC_Subscription $renewal_order
     */
    public function update_failing_payment_method($subscription, $renewal_order)
    {
        $subscription->update_meta_data('_mollie_customer_id', $renewal_order->mollie_customer_id);
        $subscription->update_meta_data('_mollie_payment_id', $renewal_order->mollie_payment_id);
        $subscription->save();
    }
    /**
     * @param $mollie_customer_id
     * @param $mollie_payment_id
     * @param $subscription
     *
     * @return string
     */
    public function restore_mollie_customer_id_and_mandate($mollie_customer_id, $mollie_payment_id, $subscription)
    {
        try {
            // Get full payment object from Mollie API
            $payment_object_resource = $this->paymentFactory->getPaymentObject($mollie_payment_id);
            //
            // If there is no known customer ID, try to get it from the API
            //
            if (empty($mollie_customer_id)) {
                $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: no valid customer ID found, trying to restore from Mollie API payment (' . $mollie_payment_id . ').');
                // Try to get the customer ID from the payment object
                $mollie_customer_id = $payment_object_resource->getMollieCustomerIdFromPaymentObject($mollie_payment_id);
                if (empty($mollie_customer_id)) {
                    $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: stopped processing, no customer ID found for this customer/payment combination.');
                    return $mollie_customer_id;
                }
                $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: customer ID (' . $mollie_customer_id . ') found, verifying status of customer and mandate(s).');
            }
            //
            // Check for valid mandates
            //
            $apiKey = $this->dataService->getApiKey();
            // Get the WooCommerce payment gateway for this subscription
            $gateway = wc_get_payment_gateway_by_order($subscription);
            if (!$gateway || !mollieWooCommerceIsMollieGateway($gateway)) {
                $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: stopped processing, not a Mollie payment gateway, could not restore customer ID.');
                return $mollie_customer_id;
            }
            $gatewayId = $gateway->id;
            $mollie_method = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            // Check that the first payment method is related to SEPA Direct Debit and update
            if (in_array($gatewayId, self::METHODS_NEEDING_UPDATE, \true)) {
                $mollie_method = self::DIRECTDEBIT;
            }
            // Get all mandates for the customer
            $mandates = $this->apiHelper->getApiClient($apiKey)->customers->get($mollie_customer_id);
            // Check credit card payments and mandates
            if ($mollie_method === 'creditcard' && !$mandates->hasValidMandateForMethod($mollie_method)) {
                $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: failed! No valid mandate for payment method ' . $mollie_method . ' found.');
                return $mollie_customer_id;
            }
            // Get a Payment object from Mollie to check for paid status
            $payment_object = $payment_object_resource->getPaymentObject($mollie_payment_id);
            // Extra check that first payment was not sequenceType first
            $sequence_type = $payment_object_resource->getSequenceTypeFromPaymentObject($mollie_payment_id);
            // Check SEPA Direct Debit payments and mandates
            if ($mollie_method === self::DIRECTDEBIT && !$mandates->hasValidMandateForMethod($mollie_method) && $payment_object->isPaid() && $sequence_type === 'oneoff') {
                $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: no valid mandate for payment method ' . $mollie_method . ' found, trying to create one.');
                $options = $payment_object_resource->getMollieCustomerIbanDetailsFromPaymentObject($mollie_payment_id);
                // consumerName can be empty for Bancontact payments, in that case use the WooCommerce customer name
                if (empty($options['consumerName'])) {
                    $billing_first_name = $subscription->get_billing_first_name();
                    $billing_last_name = $subscription->get_billing_last_name();
                    $options['consumerName'] = $billing_first_name . ' ' . $billing_last_name;
                }
                // Set method
                $options['method'] = $mollie_method;
                $customer = $this->apiHelper->getApiClient($apiKey)->customers->get($mollie_customer_id);
                $this->apiHelper->getApiClient($apiKey)->mandates->createFor($customer, $options);
                $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: mandate created successfully, customer restored.');
            } else {
                $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: the subscription doesn\'t meet the conditions for a mandate restore.');
            }
            return $mollie_customer_id;
        } catch (ApiException $e) {
            $this->logger->debug(__METHOD__ . ' - Subscription ' . $subscription->get_id() . ' renewal payment: customer id and mandate restore failed. ' . $e->getMessage());
            return $mollie_customer_id;
        }
    }
    /**
     * TODO this is still used in the service callback
     * Check if the gateway is available in checkout
     *
     * @return bool
     */
    public function is_available($gateway): bool
    {
        if (!$this->checkEnabledNorDirectDebit($gateway)) {
            return \false;
        }
        if (!$this->cartAmountAvailable()) {
            return \true;
        }
        $status = parent::is_available($gateway);
        // Do extra checks if WooCommerce Subscriptions is installed
        $orderTotal = WC()->cart && WC()->cart->get_total('edit');
        return $this->subscriptionObject->isAvailableForSubscriptions($status, $this, $orderTotal, $gateway);
    }
    /**
     * @param $subscriptionParentOrder
     * @return bool
     */
    protected function initialPaymentUsedOrderAPI($subscriptionParentOrder): bool
    {
        if (!$subscriptionParentOrder) {
            return \false;
        }
        $orderIdMeta = $subscriptionParentOrder->get_meta('_mollie_order_id');
        $parentOrderMeta = $orderIdMeta ?: PaymentProcessor::PAYMENT_METHOD_TYPE_PAYMENT;
        return strpos($parentOrderMeta, 'ord_') !== \false;
    }
    /**
     * @param int $renewal_order_id
     * @param $customer_id
     * @param \Mollie\Api\MollieApiClient $mollieApiClient
     * @param $mandateId
     * @param bool $isRenewalMethodDirectDebit
     * @param $data
     * @param bool $validMandate
     * @return array
     * @throws ApiException
     */
    protected function usePreviousMandate(int $renewal_order_id, $customer_id, \Mollie\Api\MollieApiClient $mollieApiClient, $mandateId, bool $isRenewalMethodDirectDebit, $data, bool $validMandate, $gateway): array
    {
        $this->logger->debug($gateway->id . ': Found mandate ID for renewal order ' . $renewal_order_id . ' with customer ID ' . $customer_id);
        $mandate = $mollieApiClient->customers->get($customer_id)->getMandate($mandateId);
        if ($mandate->status === 'valid') {
            $data['method'] = $mandate->method;
            $data['mandateId'] = $mandateId;
            $validMandate = \true;
        }
        return [$mandate, $data, $validMandate];
    }
    /**
     * @param int $renewal_order_id
     * @param $customer_id
     * @param \Mollie\Api\MollieApiClient $mollieApiClient
     * @param bool $validMandate
     * @param $data
     * @param $renewalOrderMethod
     * @return array
     * @throws ApiException
     */
    protected function useAnyValidMandate(int $renewal_order_id, $customer_id, \Mollie\Api\MollieApiClient $mollieApiClient, bool $validMandate, $data, $renewalOrderMethod, $gateway): array
    {
        // Get all mandates for the customer ID
        $this->logger->debug($gateway->id . ': Try to get all mandates for renewal order ' . $renewal_order_id . ' with customer ID ' . $customer_id);
        $mandates = $mollieApiClient->customers->get($customer_id)->mandates();
        foreach ($mandates as $mandate) {
            if ($mandate->status === 'valid') {
                $validMandate = \true;
                $data['method'] = $mandate->method;
                if ($mandate->method === $renewalOrderMethod) {
                    $data['method'] = $mandate->method;
                    break;
                }
            }
        }
        return [$validMandate, $data];
    }
}
