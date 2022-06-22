<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Subscription;

use Exception;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieSubscription;
use Mollie\WooCommerce\Payment\OrderInstructionsService;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\SDK\InvalidApiKey;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;
use WC_Order;

class MollieSubscriptionGateway extends MolliePaymentGateway
{

    const PAYMENT_TEST_MODE = 'test';
    const METHODS_NEEDING_UPDATE = ['mollie_wc_gateway_bancontact',
        'mollie_wc_gateway_belfius',
        'mollie_wc_gateway_directdebit',
        'mollie_wc_gateway_eps',
        'mollie_wc_gateway_giropay',
        'mollie_wc_gateway_ideal',
        'mollie_wc_gateway_kbc',
        'mollie_wc_gateway_sofort'];
    const DIRECTDEBIT = 'directdebit';


    protected $isSubscriptionPayment = false;
    protected $apiHelper;
    public $settingsHelper;
    /**
     * @var MollieSubscription
     */
    protected $subscriptionObject;

    /**
     * AbstractSubscription constructor.
     */
    public function __construct(
        PaymentMethodI $paymentMethod,
        PaymentService $paymentService,
        OrderInstructionsService $orderInstructionsService,
        MollieOrderService $mollieOrderService,
        Data $dataService,
        Logger $logger,
        NoticeInterface $notice,
        HttpResponse $httpResponse,
        Settings $settingsHelper,
        MollieObject $mollieObject,
        PaymentFactory $paymentFactory,
        string $pluginId,
        Api $apiHelper
    ) {

        parent::__construct(
            $paymentMethod,
            $paymentService,
            $orderInstructionsService,
            $mollieOrderService,
            $dataService,
            $logger,
            $notice,
            $httpResponse,
            $mollieObject,
            $paymentFactory,
            $pluginId
        );

        $this->apiHelper = $apiHelper;
        $this->subscriptionObject = new mollieSubscription(
            $pluginId,
            $apiHelper,
            $settingsHelper,
            $dataService,
            $logger
        );

        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2);

            // A resubscribe order to record a customer resubscribing to an expired or cancelled subscription.
            add_action('wcs_resubscribe_order_created', [ $this, 'delete_resubscribe_meta' ], 10);

            // After creating a renewal order to record a scheduled subscription payment with the same post meta, order items etc.
             add_action('wcs_renewal_order_created', [ $this, 'delete_renewal_meta' ], 10);

            add_action('woocommerce_subscription_failing_payment_method_updated_mollie', [ $this, 'update_failing_payment_method' ], 10, 2);

            add_filter('woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2);
            add_filter('woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 2);
        }
        if ($this->paymentMethod->getProperty('Subscription')) {
            $this->initSubscriptionSupport();
        }
    }

    /**
     *
     */
    protected function initSubscriptionSupport()
    {
        $supportSubscriptions = [
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
            'subscription_payment_method_change',
            'subscription_payment_method_change_admin',
            'subscription_payment_method_change_customer',
        ];

        $this->supports = array_merge($this->supports, $supportSubscriptions);
    }

    /**
     * @param $order_id
     * @return array
     * @throws InvalidApiKey
     */
    public function process_subscription_payment($order_id)
    {
        $this->isSubscriptionPayment = true;
        return parent::process_payment($order_id);
    }


    /**
     * @param $renewal_order
     *
     * @return void
     */
    public function update_subscription_status_for_direct_debit($renewal_order)
    {
        // Get renewal order id
        $renewal_order_id = $renewal_order->get_id();

        // Make sure order is a renewal order with subscription
        if (wcs_order_contains_renewal($renewal_order_id)) {
            // Get required information about order and subscription
            $renewal_order = wc_get_order($renewal_order_id);
            $mollie_payment_id = $renewal_order->get_meta('_mollie_payment_id', $single = true);
            $subscription_id = $renewal_order->get_meta('_subscription_renewal', $single = true);
            $subscription = wcs_get_subscription($subscription_id);
            $current_method = $subscription->get_payment_method();

            // Check that subscription status isn't already active
            if ($subscription->get_status() === 'active') {
                return;
            }

            // Check that payment method is SEPA Direct Debit or similar
            $methods_needing_update =  self::METHODS_NEEDING_UPDATE;

            if (in_array($current_method, $methods_needing_update) === false) {
                return;
            }

            // Check if WooCommerce Subscriptions Failed Recurring Payment Retry System is in-use, if it is, don't update subscription status
            if (class_exists('WCS_Retry_Manager') && \WCS_Retry_Manager::is_retry_enabled() && $subscription->get_date('payment_retry') > 0) {
                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - WooCommerce Subscriptions Failed Recurring Payment Retry System in use, not updating subscription status to Active!');

                return;
            }

            // Check that a new payment is made for renewal order
            if ($mollie_payment_id === null) {
                return;
            }

            // Update subscription to Active
            try {
                $subscription->update_status('active');
            } catch (Exception $e) {
                // Already logged by WooCommerce Subscriptions
                $this->logger->log(LogLevel::DEBUG, 'Could not update subscription ' . $subscription_id . ' status:' . $e->getMessage());
            }

            // Add order note to subscription explaining the change
            $subscription->add_order_note(
            /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
                __('Updated subscription from \'On hold\' to \'Active\' until payment fails, because a SEPA Direct Debit payment takes some time to process.', 'mollie-payments-for-woocommerce')
            );
        }
        return;
    }

    /**
     * @param          $renewal_total
     * @param WC_Order $renewal_order
     *
     * @return array
     * @throws InvalidApiKey
     */
    public function scheduled_subscription_payment($renewal_total, WC_Order $renewal_order)
    {
        $this->logger->log(LogLevel::DEBUG, json_encode($renewal_order));

        if (! $renewal_order) {
            $this->logger->log(LogLevel::DEBUG, $this->id . ': Could not load renewal order or process renewal payment.');

            return  [ 'result' => 'failure' ];
        }

        $renewal_order_id = $renewal_order->get_id();

        /**
         * Action Allow developers to hook into the subscription renewal payment before it processed.
         *
         * @since 2.0.0
         *
         * @param WC_Order $renewal_order The WooCommerce order to renew.
         */
        do_action($this->pluginId . '_before_renewal_payment_created', $renewal_order);

        $this->logger->log(LogLevel::DEBUG, $this->id . ': Try to create renewal payment for renewal order ' . $renewal_order_id);
        $this->paymentService->setGateway($this);
        $initial_order_status = $this->paymentMethod->getInitialOrderStatus();

        /**
         * Overwrite plugin-wide the initial order status.
         *
         * @since 2.0.0
         *
         * @param string $initial_order_status initial order status.
         */
        $initial_order_status = apply_filters($this->pluginId . '_initial_order_status', $initial_order_status);

        /**
         * Overwrite gateway-wide the initial order status.
         *
         * @since 2.0.0
         *
         * @param string $initial_order_status initial order status.
         */
        $initial_order_status = apply_filters($this->pluginId . '_initial_order_status_' . $this->id, $initial_order_status);

        // Get Mollie customer ID
        $customer_id = $this->getOrderMollieCustomerId($renewal_order);

        $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order->get_id());
        $subscription = array_pop($subscriptions); // Just need one valid subscription
        $subscription_mollie_payment_id = $subscription->get_meta('_mollie_payment_id');
        $subcriptionParentOrder = $subscription->get_parent();
        $mandateId = !empty($subcriptionParentOrder) ? $subcriptionParentOrder->get_meta('_mollie_mandate_id') : null;

        if (! empty($subscription_mollie_payment_id) && ! empty($subscription)) {
            $customer_id = $this->restore_mollie_customer_id_and_mandate($customer_id, $subscription_mollie_payment_id, $subscription);
        }

        // Get all data for the renewal payment
        $initialPaymentUsedOrderAPI = $this->initialPaymentUsedOrderAPI($subcriptionParentOrder);
        $data = $this->subscriptionObject->getRecurringPaymentRequestData($renewal_order, $customer_id, $initialPaymentUsedOrderAPI);

        /**
         * Allow filtering the renewal payment data.
         *
         * @since 2.5.2
         *
         * @param array $data data to send to Mollie's API.
         * @param string $renewal_order the order to be renewed.
         */
        $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $renewal_order);

        // Create a renewal payment
        try {
            /**
             * Action hook before creating the payment.
             *
             * @since 3.0.0
             *
             * @param array $data Data for the payment.
             * @param WC_Order $renewal_order The WooCommerce order to renew.
             */
            do_action($this->pluginId . '_create_payment', $data, $renewal_order);
            $apiKey = $this->dataService->getApiKey();
            $mollieApiClient = $this->apiHelper->getApiClient($apiKey);
            $validMandate = false;
            $renewalOrderMethod = $renewal_order->get_payment_method();
            $isRenewalMethodDirectDebit = in_array($renewalOrderMethod, self::METHODS_NEEDING_UPDATE);
            $renewalOrderMethod = str_replace("mollie_wc_gateway_", "", $renewalOrderMethod);

            try {
                if (!empty($mandateId)) {
                    $this->logger->log(LogLevel::DEBUG, $this->id . ': Found mandate ID for renewal order ' . $renewal_order_id . ' with customer ID ' . $customer_id);

                    $mandate =  $mollieApiClient->customers->get($customer_id)->getMandate($mandateId);
                    $bothDirectDebit = $mandate->method === self::DIRECTDEBIT
                        && $isRenewalMethodDirectDebit;
                    $bothCreditcard = $mandate->method !== self::DIRECTDEBIT
                        && !$isRenewalMethodDirectDebit;
                    $samePaymentMethodAsMandate = $bothDirectDebit || $bothCreditcard;
                    if ($mandate->status === 'valid' && $samePaymentMethodAsMandate) {
                        $data['method'] = $mandate->method;
                        $data['mandateId'] = $mandateId;
                        $validMandate = true;
                    }
                }
                if(!$validMandate){
                    // Get all mandates for the customer ID
                    $this->logger->log(LogLevel::DEBUG,$this->id . ': Try to get all mandates for renewal order ' . $renewal_order_id . ' with customer ID ' . $customer_id );
                    $mandates =  $mollieApiClient->customers->get($customer_id)->mandates();
                    foreach ($mandates as $mandate) {
                        if ($mandate->status === 'valid') {
                            $validMandate = true;
                            $data['method'] = $mandate->method;
                            if($mandate->method === $renewalOrderMethod){
                                $data['method'] = $mandate->method;
                                break;
                            }
                        }
                    }
                }
            } catch (ApiException $e) {
                throw new ApiException(sprintf(__('The customer (%s) could not be used or found. ' . $e->getMessage(), 'mollie-payments-for-woocommerce-mandate-problem'), $customer_id));
            }

            // Check that there is at least one valid mandate
            try {
                if ( $validMandate ) {
                    $payment = $this->apiHelper->getApiClient($apiKey)->payments->create( $data );
                    //check the payment method is the one in the order, if not we want this payment method in the order MOL-596
                    $paymentMethodUsed = 'mollie_wc_gateway_'.$payment->method;
                    if($paymentMethodUsed !== $renewalOrderMethod){
                        $renewal_order->set_payment_method($paymentMethodUsed);
                    }

                    //update the valid mandate for this order
                    if ((property_exists($payment, 'mandateId')
                            && $payment->mandateId !== null)
                        && $payment->mandateId !== $mandateId
                        && !empty($subcriptionParentOrder)
                    ) {
                        $this->logger->log(LogLevel::DEBUG,"{$this->id}: updating to mandate {$payment->mandateId}");
                        $subcriptionParentOrder->update_meta_data(
                            '_mollie_mandate_id',
                            $payment->mandateId
                        );
                        $subcriptionParentOrder->save();
                    }
                } else {
                    throw new ApiException(sprintf(__('The customer (%s) does not have a valid mandate.', 'mollie-payments-for-woocommerce-mandate-problem'), $customer_id));
                }
            } catch (ApiException $e) {
                throw $e;
            }

            // Update first payment method to actual recurring payment method used for renewal order
            $this->updateFirstPaymentMethodToRecurringPaymentMethod($renewal_order, $renewal_order_id, $payment);

            // Log successful creation of payment
            $this->logger->log(LogLevel::DEBUG, $this->id . ': Renewal payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $renewal_order_id . ' payment json response: ' . json_encode($payment));

            // Unset & set active Mollie payment
            // Get correct Mollie Payment Object
            $payment_object = $this->paymentFactory->getPaymentObject($payment);
            $payment_object->unsetActiveMolliePayment($renewal_order_id);
            $payment_object->setActiveMolliePayment($renewal_order_id);

            // Set Mollie customer
            $this->dataService->setUserMollieCustomerIdAtSubscription($renewal_order_id, $payment_object::$customerId);

            /**
             * Action hook after creating the payment.
             *
             * @since 2.0.0
             *
             * @param bool|MollieOrder|MolliePayment $payment The payment.
             * @param WC_Order $renewal_order The WooCommerce order processed.
             */
            do_action($this->pluginId . '_payment_created', $payment, $renewal_order);

            // Update order status and add order note
            $this->_updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment);

            // Update status of subscriptions with payment method SEPA Direct Debit or similar
            $this->update_subscription_status_for_direct_debit($renewal_order);

            /**
             * Action hook after creating the renewal.
             *
             * @since 2.0.0
             *
             * @param bool|MollieOrder|MolliePayment $payment The payment received.
             * @param WC_Order $renewal_order The WooCommerce order processed.
             */
            do_action($this->pluginId . '_after_renewal_payment_created', $payment, $renewal_order);

            return [
                'result' => 'success',
            ];
        } catch (ApiException $e) {
            $this->logger->log(LogLevel::DEBUG, $this->id . ': Failed to create payment for order ' . $renewal_order_id . ': ' . $e->getMessage());

            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(__('Could not create %s renewal payment.', 'mollie-payments-for-woocommerce'), $this->title);
            $message .= ' ' . $e->getMessage();
            $renewal_order->update_status('failed', $message);
        }

        return ['result' => 'failure'];
    }

    public function isTestModeEnabledForRenewalOrder($order)
    {
        $result = false;
        $subscriptions = [];
        if (wcs_order_contains_renewal($order->get_id())) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order->get_id());
        }

        foreach ($subscriptions as $subscription) {
            $paymentMode = $subscription->get_meta('_mollie_payment_mode', true);

            // If subscription does not contain the mode, try getting it from the parent order
            if (empty($paymentMode)) {
                $parent_order = new \WC_Order($subscription->get_parent_id());
                $paymentMode = $parent_order->get_meta('_mollie_payment_mode', true);
            }

            if ($paymentMode === self::PAYMENT_TEST_MODE) {
                $result = true;
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

        $methods_needing_update =  [
            'mollie_wc_gateway_bancontact',
            'mollie_wc_gateway_belfius',
            'mollie_wc_gateway_eps',
            'mollie_wc_gateway_giropay',
            'mollie_wc_gateway_ideal',
            'mollie_wc_gateway_kbc',
            'mollie_wc_gateway_sofort',
        ];

        $current_method = get_post_meta($renewal_order_id, '_payment_method', $single = true);
        if (in_array($current_method, $methods_needing_update) && $payment->method === self::DIRECTDEBIT) {
            try {
                $renewal_order->set_payment_method('mollie_wc_gateway_directdebit');
                $renewal_order->set_payment_method_title('SEPA Direct Debit');
                $renewal_order->save();
            } catch (\WC_Data_Exception $e) {
                $this->logger->log(LogLevel::DEBUG, 'Updating payment method to SEPA Direct Debit failed for renewal order: ' . $renewal_order_id);
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
        return $order->get_meta('_mollie_customer_id', true);
    }

    /**
     * @param $renewal_order
     * @param $initial_order_status
     * @param $payment
     */
    protected function _updateScheduledPaymentOrder($renewal_order, $initial_order_status, $payment)
    {
        $this->mollieOrderService->updateOrderStatus(
            $renewal_order,
            $initial_order_status,
            __('Awaiting payment confirmation.', 'mollie-payments-for-woocommerce') . "\n"
        );

        $payment_method_title = $this->paymentMethod->getProperty('title');

        $renewal_order->add_order_note(sprintf(
        /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
            __('%1$s payment started (%2$s).', 'mollie-payments-for-woocommerce'),
            $payment_method_title,
            $payment->id . ($payment->mode === 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
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
     * @param $renewal_order
     * @return mixed
     */
    public function delete_renewal_meta($renewal_order)
    {
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
    public function add_subscription_payment_meta($payment_meta, $subscription)
    {
        $mollie_payment_id = $subscription->get_meta('_mollie_payment_id', true);
        $mollie_payment_mode = $subscription->get_meta('_mollie_payment_mode', true);
        $mollie_customer_id = $subscription->get_meta('_mollie_customer_id', true);

        $payment_meta[ $this->id ] =  [
            'post_meta' =>  [
                '_mollie_payment_id' =>  [
                    'value' => $mollie_payment_id,
                    'label' => 'Mollie Payment ID',
                ],
                '_mollie_payment_mode' =>  [
                    'value' => $mollie_payment_mode,
                    'label' => 'Mollie Payment Mode',
                ],
                '_mollie_customer_id' =>  [
                    'value' => $mollie_customer_id,
                    'label' => 'Mollie Customer ID',
                ],
            ],
        ];

        return $payment_meta;
    }

    /**
     * @param $payment_method_id
     * @param $payment_meta
     * @throws Exception
     */
    public function validate_subscription_payment_meta($payment_method_id, $payment_meta)
    {
        if ($this->id === $payment_method_id) {
            // Check that a Mollie Customer ID is entered
            if (! isset($payment_meta['post_meta']['_mollie_customer_id']['value']) || empty($payment_meta['post_meta']['_mollie_customer_id']['value'])) {
                throw new Exception('A "_mollie_customer_id" value is required.');
            }
        }
    }

    /**
     * @param $subscription
     * @param $renewal_order
     */
    public function update_failing_payment_method($subscription, $renewal_order)
    {
        $subscription = wc_get_order($subscription->id);
        $subscription->update_meta_data('_mollie_customer_id', $renewal_order->mollie_customer_id);
        $subscription->update_meta_data('_mollie_payment_id', $renewal_order->mollie_payment_id);
        $subscription->save();
    }

    /**
     * @param int $order_id
     *
     * @return array
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws InvalidApiKey
     */
    public function process_payment($order_id)
    {
        $this->addWcSubscriptionsFiltersForPayment();
        $isSubscription = $this->dataService->isSubscription($order_id);
        if ($isSubscription) {
            $this->paymentService->setGateway($this);
            $result = $this->process_subscription_payment($order_id);
            return $result;
        }

        return parent::process_payment($order_id);
    }

    protected function addWcSubscriptionsFiltersForPayment(): void
    {
        add_filter(
            $this->pluginId . '_is_subscription_payment',
            function ($isSubscription, $orderId) {
                if ($this->dataService->isWcSubscription($orderId)) {
                    add_filter(
                        $this->pluginId . '_is_automatic_payment_disabled',
                        function ($filteredOption) {
                            if('yes' == get_option(
                                    \WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments'
                                )){
                                return true;
                            }
                            return $filteredOption;
                        }
                    );
                    return true;
                }
                return $isSubscription;
            },
            10,
            2
        );
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
            // Get subscription ID
            $subscription_id = $subscription->get_id();

            // Get full payment object from Mollie API
            $payment_object_resource = $this->paymentFactory->getPaymentObject($mollie_payment_id);

            //
            // If there is no known customer ID, try to get it from the API
            //

            if (empty($mollie_customer_id)) {
                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: no valid customer ID found, trying to restore from Mollie API payment (' . $mollie_payment_id . ').');

                // Try to get the customer ID from the payment object
                $mollie_customer_id = $payment_object_resource->getMollieCustomerIdFromPaymentObject($mollie_payment_id);

                if (empty($mollie_customer_id)) {
                    $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: stopped processing, no customer ID found for this customer/payment combination.');

                    return $mollie_customer_id;
                }

                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: customer ID (' . $mollie_customer_id . ') found, verifying status of customer and mandate(s).');
            }

            //
            // Check for valid mandates
            //
            $apiKey = $this->dataService->getApiKey();

            // Get the WooCommerce payment gateway for this subscription
            $gateway = wc_get_payment_gateway_by_order($subscription);

            if (! $gateway || ! ( $gateway instanceof MolliePaymentGateway )) {
                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: stopped processing, not a Mollie payment gateway, could not restore customer ID.');

                return $mollie_customer_id;
            }

            $mollie_method = $gateway->paymentMethod->getProperty('id');

            // Check that first payment method is related to SEPA Direct Debit and update
            $methods_needing_update =  [
                'bancontact',
                'belfius',
                'eps',
                'giropay',
                'ideal',
                'kbc',
                'sofort',
            ];

            if (in_array($mollie_method, $methods_needing_update) != false) {
                $mollie_method = self::DIRECTDEBIT;
            }

            // Get all mandates for the customer
            $mandates = $this->apiHelper->getApiClient($apiKey)->customers->get($mollie_customer_id);

            // Check credit card payments and mandates
            if ($mollie_method === 'creditcard' && ! $mandates->hasValidMandateForMethod($mollie_method)) {
                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: failed! No valid mandate for payment method ' . $mollie_method . ' found.');

                return $mollie_customer_id;
            }

            // Get a Payment object from Mollie to check for paid status
            $payment_object = $payment_object_resource->getPaymentObject($mollie_payment_id);

            // Extra check that first payment was not sequenceType first
            $sequence_type = $payment_object_resource->getSequenceTypeFromPaymentObject($mollie_payment_id);

            // Check SEPA Direct Debit payments and mandates
            if ($mollie_method === self::DIRECTDEBIT && ! $mandates->hasValidMandateForMethod($mollie_method) && $payment_object->isPaid() && $sequence_type === 'oneoff') {
                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: no valid mandate for payment method ' . $mollie_method . ' found, trying to create one.');

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

                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: mandate created successfully, customer restored.');
            } else {
                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: the subscription doesn\'t meet the conditions for a mandate restore.');
            }

            return $mollie_customer_id;
        } catch (ApiException $e) {
            $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Subscription ' . $subscription_id . ' renewal payment: customer id and mandate restore failed. ' . $e->getMessage());

            return $mollie_customer_id;
        }
    }

    /**
     * Check if the gateway is available in checkout
     *
     * @return bool
     */
    public function is_available(): bool
    {
        if(!$this->checkEnabledNorDirectDebit()){
            return false;
        }
        if(!$this->cartAmountAvailable()){
            return true;
        }
        $status =  parent::is_available();
        // Do extra checks if WooCommerce Subscriptions is installed
        $orderTotal = $this->get_order_total();
        return $this->subscriptionObject->isAvailableForSubscriptions($status, $this, $orderTotal);
    }

    /**
     * @param $subcriptionParentOrder
     * @return bool
     */
    protected function initialPaymentUsedOrderAPI($subcriptionParentOrder): bool
    {
        if(!$subcriptionParentOrder){
            return false;
        }
        $orderIdMeta = $subcriptionParentOrder->get_meta('_mollie_order_id');

        $parentOrderMeta = $orderIdMeta?: PaymentService::PAYMENT_METHOD_TYPE_PAYMENT;

        return strpos($parentOrderMeta, 'ord_') !== false;
    }
}
