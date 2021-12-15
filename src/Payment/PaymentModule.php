<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

class PaymentModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;

    /**
     * @var mixed
     */
    protected $httpResponse;
    /**
     * @var mixed
     */
    protected $logger;
    protected $apiHelper;
    /**
     * @var mixed
     */
    protected $settingsHelper;
    protected $pluginId;
    /**
     * @var mixed
     */
    protected $gatewayClassnames;


    public function services(): array
    {
        return [
           PaymentFactory::class => static function (ContainerInterface $container): PaymentFactory {
               $data = $container->get('core.data_helper');
               $apiHelper = $container->get('core.api_helper');
               $settingsHelper = $container->get('settings.settings_helper');
               $pluginId = $container->get('core.plugin_id');
               $logger = $container->get(Logger::class);
               return new PaymentFactory($data, $apiHelper, $settingsHelper, $pluginId, $logger);
           },
           MollieObject::class => static function (ContainerInterface $container): MollieObject {
               $logger = $container->get(Logger::class);
               $data = $container->get('core.data_helper');
               $apiHelper = $container->get('core.api_helper');
               $paymentFactory = $container->get(PaymentFactory::class);
               $settingsHelper = $container->get('settings.settings_helper');
               return new MollieObject($data, $logger, $paymentFactory, $apiHelper, $settingsHelper);
           }
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        $this->httpResponse = $container->get('SDK.HttpResponse');
        $this->logger = $container->get(Logger::class);
        $this->apiHelper = $container->get('core.api_helper');
        $this->settingsHelper = $container->get('settings.settings_helper');
        $this->pluginId = $container->get('core.plugin_id');
        $this->gatewayClassnames = $container->get('gateway.classnames');
        // Listen to return URL call
        add_action('woocommerce_api_mollie_return', [ $this, 'onMollieReturn' ]);
        add_action('template_redirect', [ $this, 'mollieReturnRedirect' ]);

        // Show Mollie instructions on order details page
        add_action('woocommerce_order_details_after_order_table', [ $this, 'onOrderDetails' ], 10, 1);

        // Cancel order at Mollie (for Orders API/Klarna)
        add_action('woocommerce_order_status_cancelled', [ $this, 'cancelOrderAtMollie' ]);

        // Capture order at Mollie (for Orders API/Klarna)
        add_action('woocommerce_order_status_completed', [ $this, 'shipAndCaptureOrderAtMollie' ]);

        add_filter(
            'woocommerce_cancel_unpaid_order',
            [ $this, 'maybeLetWCCancelOrder' ],
            9,
            2
        );

        $this->handleExpiryDateCancelation();

        add_action(
            OrderItemsRefunder::ACTION_AFTER_REFUND_ORDER_ITEMS,
            [$this, 'addOrderNoteForRefundCreated'],
            10,
            3
        );
        add_action(
            OrderItemsRefunder::ACTION_AFTER_CANCELED_ORDER_ITEMS,
            [$this, 'addOrderNoteForCancelledLineItems'],
            10,
            2
        );

        return true;
    }

    public function maybeLetWCCancelOrder($willCancel, $order)
    {
        if (!empty($willCancel)) {
            $isMollieGateway = mollieWooCommerceIsMollieGateway($order->get_payment_method(
            ));

            $mollieDueDateEnabled = mollieWooCommerceIsGatewayEnabled($order->get_payment_method(
            ), 'activate_expiry_days_setting');
            if (!$isMollieGateway || !$mollieDueDateEnabled
            ) {
                return $willCancel;
            }

            return false;
        }
        return $willCancel;
    }

    public function cancelOrderOnExpiryDate()
    {
        $classNames = $this->gatewayClassnames;
        foreach ($classNames as $gateway) {
            $gatewayName = strtolower($gateway).'_settings';
            $gatewaySettings = get_option($gatewayName);
            $heldDuration = isset($gatewaySettings['order_dueDate'])?$gatewaySettings['order_dueDate']:0;

            if ($heldDuration < 1) {
                continue;
            }
            $heldDurationInSeconds = $heldDuration*60;
            if ($gateway == 'Mollie_WC_Gateway_BankTransfer') {
                $durationInHours = absint($heldDuration)*24;
                $durationInMinutes = $durationInHours*60;
                $heldDurationInSeconds = $durationInMinutes*60;
            }
            $args = [
                'limit' => -1,
                'status' => 'pending',
                'payment_method' => strtolower($gateway),
                'date_modified' => '<' . (time() - $heldDurationInSeconds),
                'return'=>'ids',
            ];
            $unpaid_orders =wc_get_orders($args);

            if ($unpaid_orders) {
                foreach ($unpaid_orders as $unpaid_order) {
                    $order = wc_get_order($unpaid_order);
                    add_filter('mollie-payments-for-woocommerce_order_status_cancelled', function ($newOrderStatus) {
                        return MolliePaymentGateway::STATUS_CANCELLED;
                    });
                    $order->update_status('cancelled', __('Unpaid order cancelled - time limit reached.', 'woocommerce'), true);
                    self::cancelOrderAtMollie($order->get_id());
                }
            }
        }
    }

    /**
     * @param Refund $refund
     * @param WC_Order $order
     * @param array $data
     */
    public function addOrderNoteForRefundCreated(
        Refund $refund,
        WC_Order $order,
        array $data
    ) {

        $orderNote = sprintf(
            __(
                '%1$s items refunded in WooCommerce and at Mollie.',
                'mollie-payments-for-woocommerce'
            ),
            self::extractRemoteItemsIds($data)
        );

        $order->add_order_note($orderNote);
        $this->logger->log(\WC_Log_Levels::DEBUG, $orderNote);
    }

    /**
     * @param array $data
     * @param WC_Order $order
     */
    public function addOrderNoteForCancelledLineItems(array $data, WC_Order $order)
    {
        $orderNote = sprintf(
            __(
                '%1$s items cancelled in WooCommerce and at Mollie.',
                'mollie-payments-for-woocommerce'
            ),
            self::extractRemoteItemsIds($data)
        );

        $order->add_order_note($orderNote);
        $this->logger->log(\WC_Log_Levels::DEBUG, $orderNote);
    }

    /**
     * Old Payment return url callback
     *
     */
    public function onMollieReturn()
    {
        try {
            $order = self::orderByRequest();
        } catch (RuntimeException $exc) {
            $this->httpResponse->setHttpResponseCode($exc->getCode());
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ":  {$exc->getMessage()}");
            return;
        }

        $gateway = wc_get_payment_gateway_by_order($order);
        $orderId = $order->get_id();

        if (!$gateway) {
            $gatewayName = $order->get_payment_method();

            $this->httpResponse->setHttpResponseCode(404);
            $this->logger->log(
                \WC_Log_Levels::DEBUG,
                __METHOD__ . ":  Could not find gateway {$gatewayName} for order {$orderId}."
            );
            return;
        }

        if (!($gateway instanceof MolliePaymentGateway)) {
            $this->httpResponse->setHttpResponseCode(400);
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ": Invalid gateway {get_class($gateway)} for this plugin. Order {$orderId}.");
            return;
        }

        $redirect_url = $gateway->getReturnRedirectUrlForOrder($order);

        // Add utm_nooverride query string
        $redirect_url = add_query_arg(['utm_nooverride' => 1], $redirect_url);

        $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ": Redirect url on return order {$gateway->id}, order {$orderId}: {$redirect_url}");

        wp_safe_redirect($redirect_url);
        die;
    }

    /**
     * New Payment return url callback
     *
     */
    public function mollieReturnRedirect()
    {
        if (isset($_GET['filter_flag'])) {
            $filterFlag = filter_input(INPUT_GET, 'filter_flag', FILTER_SANITIZE_STRING);
            if ($filterFlag === 'onMollieReturn') {
                self::onMollieReturn();
            }
        }
    }

    /**
     * @param WC_Order $order
     */
    public function onOrderDetails(WC_Order $order)
    {
        if (is_order_received_page()) {
            /**
             * Do not show instruction again below details on order received page
             * Instructions already displayed on top of order received page by $gateway->thankyou_page()
             *
             * @see MolliePaymentGateway::thankyou_page
             */
            return;
        }

        $gateway = wc_get_payment_gateway_by_order($order);

        if (!$gateway || !($gateway instanceof MolliePaymentGateway)) {
            return;
        }

        /** @var MolliePaymentGateway $gateway */

        $gateway->displayInstructions($order);
    }
    /**
     * Ship all order lines and capture an order at Mollie.
     *
     */
    public function shipAndCaptureOrderAtMollie($order_id)
    {
        $order = wc_get_order($order_id);

        // Does WooCommerce order contain a Mollie payment?
        if (strstr($order->get_payment_method(), 'mollie_wc_gateway_') == false) {
            return;
        }

        // To disable automatic shipping and capturing of the Mollie order when a WooCommerce order status is updated to completed,
        // store an option 'mollie-payments-for-woocommerce_disableShipOrderAtMollie' with value 1
        if (get_option($this->pluginId . '_' . 'disableShipOrderAtMollie', '0') == '1') {
            return;
        }

        $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Try to process completed order for a potential capture at Mollie.');

        // Does WooCommerce order contain a Mollie Order?
        $mollie_order_id = ( $mollie_order_id = $order->get_meta('_mollie_order_id', true) ) ? $mollie_order_id : false;
        // Is it a payment? you cannot ship a payment
        if ($mollie_order_id == false || substr($mollie_order_id, 0, 3) == 'tr_') {
            $order->add_order_note('Order contains Mollie payment method, but not a Mollie Order ID. Processing capture canceled.');
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order contains Mollie payment method, but not a Mollie Order ID. Processing capture cancelled.');

            return;
        }

        // Is test mode enabled?
        $test_mode = $this->settingsHelper->isTestModeEnabled();

        try {
            // Get the order from the Mollie API
            $mollie_order = $this->apiHelper->getApiClient($test_mode)->orders->get($mollie_order_id);

            // Check that order is Paid or Authorized and can be captured
            if ($mollie_order->isCanceled()) {
                $order->add_order_note('Order already canceled at Mollie, can not be shipped/captured.');
                $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order already canceled at Mollie, can not be shipped/captured.');

                return;
            }

            if ($mollie_order->isCompleted()) {
                $order->add_order_note('Order already completed at Mollie, can not be shipped/captured.');
                $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order already completed at Mollie, can not be shipped/captured.');

                return;
            }

            if ($mollie_order->isPaid() || $mollie_order->isAuthorized()) {
                $this->apiHelper->getApiClient($test_mode)->orders->get($mollie_order_id)->shipAll();
                $order->add_order_note('Order successfully updated to shipped at Mollie, capture of funds underway.');
                $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order successfully updated to shipped at Mollie, capture of funds underway.');

                return;
            }

            $order->add_order_note('Order not paid or authorized at Mollie yet, can not be shipped.');
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order not paid or authorized at Mollie yet, can not be shipped.');
        } catch (Mollie\Api\Exceptions\ApiException $e) {
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Processing shipment & capture failed, error: ' . $e->getMessage());
        }

        return;
    }

    /**
     * Cancel an order at Mollie.
     *
     */
    public function cancelOrderAtMollie($order_id)
    {
        $order = wc_get_order($order_id);

        // Does WooCommerce order contain a Mollie payment?
        if (strstr($order->get_payment_method(), 'mollie_wc_gateway_') == false) {
            return;
        }

        // To disable automatic canceling of the Mollie order when a WooCommerce order status is updated to canceled,
        // store an option 'mollie-payments-for-woocommerce_disableCancelOrderAtMollie' with value 1
        if (get_option($this->pluginId . '_' . 'disableCancelOrderAtMollie', '0') == '1') {
            return;
        }

        $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Try to process cancelled order at Mollie.');

        $mollie_order_id = ( $mollie_order_id = $order->get_meta('_mollie_order_id', true) ) ? $mollie_order_id : false;

        if ($mollie_order_id == false) {
            $order->add_order_note('Order contains Mollie payment method, but not a valid Mollie Order ID. Canceling order failed.');
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order contains Mollie payment method, but not a valid Mollie Order ID. Canceling order failed.');

            return;
        }

        // Is test mode enabled?
        $test_mode = $this->settingsHelper->isTestModeEnabled();

        try {
            // Get the order from the Mollie API
            $mollie_order = $this->apiHelper->getApiClient($test_mode)->orders->get($mollie_order_id);

            // Check that order is not already canceled at Mollie
            if ($mollie_order->isCanceled()) {
                $order->add_order_note('Order already canceled at Mollie, can not be canceled again.');
                $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order already canceled at Mollie, can not be canceled again.');

                return;
            }

            // Check that order has the correct status to be canceled
            if ($mollie_order->isCreated() || $mollie_order->isAuthorized() || $mollie_order->isShipping()) {
                $this->apiHelper->getApiClient($test_mode)->orders->get($mollie_order_id)->cancel();
                $order->add_order_note('Order also cancelled at Mollie.');
                $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order cancelled in WooCommerce, also cancelled at Mollie.');

                return;
            }

            $order->add_order_note('Order could not be canceled at Mollie, because order status is ' . $mollie_order->status . '.');
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Order could not be canceled at Mollie, because order status is ' . $mollie_order->status . '.');
        } catch (Mollie\Api\Exceptions\ApiException $e) {
            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $order_id . ' - Updating order to canceled at Mollie failed, error: ' . $e->getMessage());
        }

        return;
    }

    public function handleExpiryDateCancelation()
    {
        //todo this doesnt work
        /*if(!mollieWooCommercIsExpiryDateEnabled()){
            as_unschedule_action( 'mollie_woocommerce_cancel_unpaid_orders' );
            return;
        }
        $canSchedule = function_exists('as_schedule_single_action');
        if ($canSchedule) {
            if ( false === as_next_scheduled_action( 'mollie_woocommerce_cancel_unpaid_orders' ) ) {
                as_schedule_recurring_action( time(), 600, 'mollie_woocommerce_cancel_unpaid_orders');
            }

            add_action(
                'mollie_woocommerce_cancel_unpaid_orders',
                [$this, 'cancelOrderOnExpiryDate'],
                11,
                2
            );
        }*/
    }

    /**
     * Returns the order from the Request first by Id, if not by Key
     *
     * @return bool|WC_Order
     */
    public function orderByRequest()
    {
        $orderId = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
        $key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING) ?: null;
        $order = wc_get_order($orderId);

        if (!$order) {
            $order = wc_get_order(wc_get_order_id_by_order_key($key));
        }

        if (!$order) {
            throw new RuntimeException(
                "Could not find order by order Id {$orderId}",
                404
            );
        }

        if (!$order->key_is_valid($key)) {
            throw new RuntimeException(
                "Invalid key given. Key {$key} does not match the order id: {$orderId}",
                401
            );
        }

        return $order;
    }

    private function extractRemoteItemsIds(array $data)
    {
        if (empty($data['lines'])) {
            return [];
        }

        return implode(',', wp_list_pluck($data['lines'], 'id'));
    }
}
