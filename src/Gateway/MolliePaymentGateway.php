<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use InvalidArgumentException;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\SequenceType;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\OrderInstructionsService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Log\LoggerInterface as Logger;
use UnexpectedValueException;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

class MolliePaymentGateway extends WC_Payment_Gateway implements MolliePaymentGatewayI
{
    /**
     * @var bool
     */
    protected static $alreadyDisplayedAdminInstructions = false;
    protected static $alreadyDisplayedCustomerInstructions = false;
    /**
     * Recurring total, zero does not define a recurring total
     *
     * @var int
     */
    protected $recurring_totals = 0;
    /**
     * @var PaymentMethodI
     */
    protected $paymentMethod;
    /**
     * @var string
     */
    protected $default_title;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * @var PaymentService
     */
    protected $paymentService;
    /**
     * @var MollieOrderService
     */
    protected $mollieOrderService;
    /**
     * @var HttpResponse
     */
    protected $httpResponse;
    /**
     * @var OrderInstructionsService
     */
    protected $orderInstructionsService;
    /**
     * @var Data
     */
    protected $dataService;
    /**
     * @var MollieObject
     */
    protected $mollieObject;
    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;
    /**
     * @var string
     */
    protected $pluginId;

    /**
     *
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
        MollieObject $mollieObject,
        PaymentFactory $paymentFactory,
        string $pluginId
    ) {

        $this->paymentMethod = $paymentMethod;
        $this->logger = $logger;
        $this->notice = $notice;
        $this->paymentService = $paymentService;
        $this->orderInstructionsService = $orderInstructionsService;
        $this->mollieOrderService = $mollieOrderService;
        $this->httpResponse = $httpResponse;
        $this->dataService = $dataService;
        $this->mollieObject = $mollieObject;
        $this->paymentFactory = $paymentFactory;
        $this->pluginId = $pluginId;

        // No plugin id, gateway id is unique enough
        $this->plugin_id = '';
        // Use gateway class name as gateway id
        $this->gatewayId();
        // Set gateway title (visible in admin)
        $this->method_title = 'Mollie - ' . $this->paymentMethod->title();
        $this->method_description = $this->paymentMethod->getProperty(
            'settingsDescription'
        );
        $this->supports = $this->paymentMethod->getProperty('supports');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->paymentMethod->title();

        $this->initDescription();
        $this->initIcon();

        if (!has_action('woocommerce_thankyou_' . $this->id)) {
            add_action(
                'woocommerce_thankyou_' . $this->id,
                [$this, 'thankyou_page']
            );
        }
        $this->mollieOrderService->setGateway($this);

        add_action(
            'woocommerce_api_' . $this->id,
            [$this->mollieOrderService, 'onWebhookAction']
        );
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );
        add_action(
            'woocommerce_email_after_order_table',
            [$this, 'displayInstructions'],
            10,
            3
        );
        add_action(
            'woocommerce_email_order_meta',
            [$this, 'displayInstructions'],
            10,
            3
        );

        // Adjust title and text on Order Received page in some cases, see issue #166
        add_filter('the_title', [$this, 'onOrderReceivedTitle'], 10, 2);
        add_filter(
            'woocommerce_thankyou_order_received_text',
            [$this, 'onOrderReceivedText'],
            10,
            2
        );
        $this->gatewayHasFields();

        $isEnabledAtWoo = $this->paymentMethod->getProperty('enabled') ?
            $this->paymentMethod->getProperty('enabled') :
            'yes';
        $this->enabled = $isEnabledAtWoo;

        if ($this->enabled && $this->paymentMethod->getProperty('filtersOnBuild')) {
            $this->paymentMethod->filtersOnBuild();
        }
    }

    public function paymentMethod(): PaymentMethodI
    {
        return $this->paymentMethod;
    }

    public function paymentService()
    {
        return $this->paymentService;
    }

    public function dataService()
    {
        return $this->dataService;
    }

    public function pluginId()
    {
        return $this->pluginId;
    }

    public function initIcon()
    {
        if ($this->paymentMethod->shouldDisplayIcon()) {
            $defaultIcon = $this->paymentMethod->getIconUrl();
            $this->icon = apply_filters(
                $this->id . '_icon_url',
                $defaultIcon
            );
        }
    }

    public function get_icon()
    {
        $output = $this->icon ?: '';
        return apply_filters('woocommerce_gateway_icon', $output, $this->id);
    }

    protected function gatewayId()
    {
        $paymentMethodId = $this->paymentMethod->getProperty('id');
        $this->id = 'mollie_wc_gateway_' . $paymentMethodId;
        return $this->id;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = $this->paymentMethod->getAllFormFields();
    }

    /**
     * Display fields below payment method in checkout
     */
    public function payment_fields()
    {
        // Display description above issuers
        parent::payment_fields();
        $this->paymentMethod->paymentFieldsStrategy($this);
    }

    /**
     * Save settings
     *
     * @since 1.0
     */
    public function init_settings()
    {
        parent::init_settings();
    }

    protected function initDescription()
    {
        $description = $this->paymentMethod->getProcessedDescription();
        $this->description = empty($description) ? false : $description;
    }

    /**
     * Check if this gateway can be used
     *
     * @return bool
     */
    public function isValidForUse(): bool
    {
        if (is_admin()) {
            if (!$this->dataService->isValidApiKeyProvided()) {
                $test_mode = $this->dataService->isTestModeEnabled();

                $this->errors[] = ($test_mode ? __(
                    'Test mode enabled.',
                    'mollie-payments-for-woocommerce'
                ) . ' ' : '') . sprintf(
                    /* translators: The surrounding %s's Will be replaced by a link to the global setting page */
                    __(
                        'No API key provided. Please %1$sset you Mollie API key%2$s first.',
                        'mollie-payments-for-woocommerce'
                    ),
                    '<a href="' . $this->dataService->getGlobalSettingsUrl() . '">',
                    '</a>'
                );

                return false;
            }

            // This should be simpler, check for specific payment method in settings, not on all pages
            if (null === $this->getMollieMethod()) {
                $this->errors[] = sprintf(
                /* translators: Placeholder 1: payment method title. The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __(
                        '%1$s not enabled in your Mollie profile. You can enable it by editing your %2$sMollie profile%3$s.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->paymentMethod->getProperty('defaultTitle'),
                    '<a href="https://my.mollie.com/dashboard/settings/profiles?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">',
                    '</a>'
                );

                return false;
            }

            if (!$this->isCurrencySupported()) {
                $this->errors[] = sprintf(
                /* translators: Placeholder 1: WooCommerce currency, placeholder 2: Supported Mollie currencies */
                    __(
                        'Current shop currency %1$s not supported by Mollie. Read more about %2$ssupported currencies and payment methods.%3$s ',
                        'mollie-payments-for-woocommerce'
                    ),
                    get_woocommerce_currency(),
                    '<a href="https://help.mollie.com/hc/en-us/articles/360003980013-Which-currencies-are-supported-and-what-is-the-settlement-currency-?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">',
                    '</a>'
                );

                return false;
            }
        }

        return true;
    }

    /**
     * @return Method|null
     */
    public function getMollieMethod()
    {
        return $this->dataService->getPaymentMethod(
            $this->paymentMethod->getProperty('id')
        );
    }

    /**
     * @return bool
     */
    protected function isCurrencySupported(): bool
    {
        return in_array(
            get_woocommerce_currency(),
            $this->getSupportedCurrencies(),
            true
        );
    }

    /**
     * @return array
     */
    protected function getSupportedCurrencies(): array
    {
        $default = [
            'AUD',
            'BGN',
            'BRL',
            'CAD',
            'CHF',
            'CZK',
            'DKK',
            'EUR',
            'GBP',
            'HKD',
            'HRK',
            'HUF',
            'ILS',
            'ISK',
            'JPY',
            'MXN',
            'MYR',
            'NOK',
            'NZD',
            'PHP',
            'PLN',
            'RON',
            'RUB',
            'SEK',
            'SGD',
            'THB',
            'TWD',
            'USD',
        ];

        return apply_filters(
            'woocommerce_' . $this->id . '_supported_currencies',
            $default
        );
    }

    /**
     * Save options in admin.
     */
    public function process_admin_options()
    {
        $this->dataService->processSettings($this);

        parent::process_admin_options();
    }

    public function admin_options()
    {
        $this->dataService->processAdminOptions($this);
    }

    /**
     * Validates the multiselect country field.
     * Overrides the one called by get_field_value() on WooCommerce abstract-wc-settings-api.php
     *
     * @param $key
     * @param $value
     *
     * @return array|string
     */
    public function validate_multi_select_countries_field($key, $value)
    {
        return is_array($value) ? array_map(
            'wc_clean',
            array_map('stripslashes', $value)
        ) : '';
    }

    /**
     * Check if the gateway is available for use
     *
     * @return bool
     */
    public function is_available(): bool
    {
        if (!$this->checkEnabledNorDirectDebit()) {
            return false;
        }
        if (!$this->cartAmountAvailable()) {
            return true;
        }

        $order_total = $this->get_order_total();
        $currency = $this->getCurrencyFromOrder();
        $billingCountry = $this->getBillingCountry();
        $paymentLocale = $this->dataService->getPaymentLocale();

        try {
            $filters = $this->dataService->getFilters(
                $currency,
                $order_total,
                $paymentLocale,
                $billingCountry
            );
        } catch (InvalidArgumentException $exception) {
            $this->logger->debug(
                $exception->getMessage()
            );
            return false;
        }

        $status = $this->isAvailableMethodInCheckout($filters);

        return $this->isAllowedBillingCountry($billingCountry, $status);
    }

    /**
     * Check if payment method is available in checkout based on amount, currency and sequenceType
     *
     * @param $filters
     *
     * @return bool
     */
    public function isAvailableMethodInCheckout($filters): bool
    {
        $useCache = true;
        $methods = $this->dataService->getApiPaymentMethods(
            $useCache,
            $filters
        );

        // Get the ID of the WooCommerce/Mollie payment method
        $woocommerce_method = $this->paymentMethod->getProperty('id');

        // Set all other payment methods to false, so they can be updated if available
        foreach ($methods as $method) {
            if ($method['id'] === $woocommerce_method) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|false|int
     */
    public function get_recurring_total()
    {
        if (isset(WC()->cart)) {
            if (!empty(WC()->cart->recurring_carts)) {
                $this->recurring_totals = []; // Reset for cached carts

                foreach (WC()->cart->recurring_carts as $cart) {
                    if (!$cart->prices_include_tax) {
                        $this->recurring_totals[] = $cart->cart_contents_total;
                    } else {
                        $this->recurring_totals[] = $cart->cart_contents_total
                            + $cart->tax_total;
                    }
                }
            } else {
                return false;
            }
        }

        return $this->recurring_totals;
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function process_payment($orderId)
    {
        $order = wc_get_order($orderId);
        if (!$order) {
            return $this->noOrderPaymentFailure($orderId);
        }
        $paymentMethod = $this->paymentMethod;
        $redirectUrl = $this->get_return_url($order);
        $this->paymentService->setGateway($this);
        return $this->paymentService->processPayment($orderId, $order, $paymentMethod, $redirectUrl);
    }

    /**
     * @param $order
     * @param $payment
     */
    public function handlePaidOrderWebhook($order, $payment)
    {
        // Duplicate webhook call
        $this->httpResponse->setHttpResponseCode(204);

        $order = wc_get_order($order);
        $order_id = $order->get_id();

        $this->logger->debug(
            __METHOD__ . ' - ' . $this->id
            . ": Order $order_id does not need a payment by Mollie (payment {$payment->id}).",
            [true]
        );
    }

    /**
     * @param WC_Order $order
     *
     * @return string
     */
    public function getReturnRedirectUrlForOrder(WC_Order $order): string
    {
        $order_id = $order->get_id();
        $debugLine = __METHOD__
            . " {$order_id}: Determine what the redirect URL in WooCommerce should be.";
        $this->logger->debug($debugLine);
        $hookReturnPaymentStatus = 'success';
        $returnRedirect = $this->get_return_url($order);
        $failedRedirect = $order->get_checkout_payment_url(false);

        $this->mollieOrderService->setGateway($this);
        if ($this->mollieOrderService->orderNeedsPayment($order)) {
            $hasCancelledMolliePayment = $this->paymentObject()
                ->getCancelledMolliePaymentId($order_id);

            if ($hasCancelledMolliePayment) {
                $order_status_cancelled_payments = $this->paymentMethod->getOrderStatusCancelledPayments();

                // If user set all cancelled payments to also cancel the order,
                // redirect to /checkout/order-received/ with a message about the
                // order being cancelled. Otherwise redirect to /checkout/order-pay/ so
                // customers can try to pay with another payment method.
                if ($order_status_cancelled_payments === 'cancelled') {
                    return $this->get_return_url($order);
                } else {
                    $this->notice->addNotice(
                        'error',
                        __(
                            'You have cancelled your payment. Please complete your order with a different payment method.',
                            'mollie-payments-for-woocommerce'
                        )
                    );
                    // Return to order payment page
                    return $failedRedirect;
                }
            }

            try {
                $payment = $this->activePaymentObject($order_id, false);
                if (
                    !$payment->isOpen()
                    && !$payment->isPending()
                    && !$payment->isPaid()
                    && !$payment->isAuthorized()
                ) {
                    $this->notice->addNotice(
                        'error',
                        __(
                            'Your payment was not successful. Please complete your order with a different payment method.',
                            'mollie-payments-for-woocommerce'
                        )
                    );
                    // Return to order payment page
                    return $failedRedirect;
                }
                if ($payment->method === "giftcard") {
                    $this->paymentMethod->debugGiftcardDetails($payment, $order);
                }
            } catch (UnexpectedValueException $exc) {
                $this->notice->addNotice(
                    'error',
                    __(
                        'Your payment was not successful. Please complete your order with a different payment method.',
                        'mollie-payments-for-woocommerce'
                    )
                );
                $exceptionMessage = $exc->getMessage();
                $debugLine = __METHOD__
                    . " Problem processing the payment. {$exceptionMessage}";
                $this->logger->debug($debugLine);
                $hookReturnPaymentStatus = 'failed';
            }
        }
        do_action(
            $this->pluginId . '_customer_return_payment_'
            . $hookReturnPaymentStatus,
            $order
        );

        /*
         * Return to order received page
         */
        return $this->get_return_url($order);
    }
    /**
     * @param $orderId
     * @return string[]
     */
    protected function noOrderPaymentFailure($orderId): array
    {
        $this->logger->debug(
            $this->id . ': Could not process payment, order ' . $orderId . ' not found.'
        );

        $this->notice->addNotice(
            'error',
            sprintf(
            /* translators: Placeholder 1: order id. */
                __(
                    'Could not load order %s',
                    'mollie-payments-for-woocommerce'
                ),
                $orderId
            )
        );

        return ['result' => 'failure'];
    }
    /**
     * Retrieve the payment object
     *
     * @return MollieObject
     */
    protected function paymentObject(): MollieObject
    {
        return $this->mollieObject;
    }

    /**
     * Retrieve the active payment object
     *
     * @param $orderId
     * @param $useCache
     *
     * @return Payment
     * @throws UnexpectedValueException
     */
    protected function activePaymentObject($orderId, $useCache): Payment
    {
        $paymentObject = $this->paymentObject();
        $activePaymentObject = $paymentObject->getActiveMolliePayment(
            $orderId,
            $useCache
        );

        if ($activePaymentObject === null) {
            throw new UnexpectedValueException(
                esc_html(sprintf("Active Payment Object is not a valid Payment Resource instance. Order ID: %s", $orderId))
            );
        }

        return $activePaymentObject;
    }

    /**
     * Process a refund if supported
     *
     * @param int    $order_id
     * @param float  $amount
     * @param string $reason
     *
     * @return bool|wp_error True or false based on success, or a WP_Error object
     * @since WooCommerce 2.2
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        // Get the WooCommerce order
        $order = wc_get_order($order_id);

        // WooCommerce order not found
        if (!$order) {
            $error_message = "Could not find WooCommerce order $order_id.";

            $this->logger->debug(
                __METHOD__ . ' - ' . $error_message
            );

            return new WP_Error('1', $error_message);
        }

        // Check if there is a Mollie Payment Order object connected to this WooCommerce order
        $payment_object_id = $this->paymentObject()->getActiveMollieOrderId(
            $order_id
        );

        // If there is no Mollie Payment Order object, try getting a Mollie Payment Payment object
        if (!$payment_object_id) {
            $payment_object_id = $this->paymentObject()
                ->getActiveMolliePaymentId($order_id);
        }

        // Mollie Payment object not found
        if (!$payment_object_id) {
            $error_message = "Can\'t process refund. Could not find Mollie Payment object id for order $order_id.";

            $this->logger->debug(
                __METHOD__ . ' - ' . $error_message
            );

            return new WP_Error('1', $error_message);
        }

        try {
            $payment_object = $this->paymentFactory
                ->getPaymentObject(
                    $payment_object_id
                );
        } catch (ApiException $exception) {
            $exceptionMessage = $exception->getMessage();
            $this->logger->debug($exceptionMessage);
            return new WP_Error('error', $exceptionMessage);
        }

        if (!$payment_object) {
            $error_message = "Can\'t process refund. Could not find Mollie Payment object data for order $order_id.";

            $this->logger->debug(
                __METHOD__ . ' - ' . $error_message
            );

            return new WP_Error('1', $error_message);
        }

        return $payment_object->refund(
            $order,
            $order_id,
            $payment_object,
            $amount,
            $reason
        );
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);

        // Order not found
        if (!$order) {
            return;
        }

        // Empty cart
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }

        // Same as email instructions, just run that
        $this->displayInstructions(
            $order,
            $admin_instructions = false,
            $plain_text = false
        );
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool     $admin_instructions (default: false)
     * @param bool     $plain_text         (default: false)
     *
     * @return void
     */
    public function displayInstructions(
        WC_Order $order,
        $admin_instructions = false,
        $plain_text = false
    ) {

        if (
            ($admin_instructions && !$this::$alreadyDisplayedAdminInstructions)
            || (!$admin_instructions && !$this::$alreadyDisplayedCustomerInstructions)
        ) {
            $order_payment_method = $order->get_payment_method();

            // Invalid gateway
            if ($this->id !== $order_payment_method) {
                return;
            }

            $payment = $this->paymentObject()->getActiveMolliePayment(
                $order->get_id()
            );

            // Mollie payment not found or invalid gateway
            if (
                !$payment
                || $payment->method !== $this->paymentMethod->getProperty('id')
            ) {
                return;
            }
            $this->orderInstructionsService->setStrategy($this);
            $instructions = $this->orderInstructionsService->executeStrategy(
                $this,
                $payment,
                $order,
                $admin_instructions
            );

            if (!empty($instructions)) {
                $instructions = wptexturize($instructions);
                //save instructions in order meta
                $order->update_meta_data(
                    '_mollie_payment_instructions',
                    $instructions
                );
                $order->save();

                if ($plain_text) {
                    echo esc_html($instructions) . PHP_EOL;
                } else {
                    echo '<section class="woocommerce-order-details woocommerce-info mollie-instructions" >';
                    echo wp_kses(wpautop($instructions), ['p' => [], 'strong' => []]) . PHP_EOL;
                    echo '</section>';
                }
            }
        }
        if ($admin_instructions && !$this::$alreadyDisplayedAdminInstructions) {
            $this::$alreadyDisplayedAdminInstructions = true;
        }
        if (!$admin_instructions && !$this::$alreadyDisplayedCustomerInstructions) {
            $this::$alreadyDisplayedCustomerInstructions = true;
        }
    }

    /**
     * @param      $title
     * @param null $id
     *
     * @return string|void
     */
    public function onOrderReceivedTitle($title, $id = null)
    {
        if (is_order_received_page() && get_the_ID() === $id) {
            global $wp;

            $order = false;
            $order_id = apply_filters(
                'woocommerce_thankyou_order_id',
                absint($wp->query_vars['order-received'])
            );
            $order_key = apply_filters(
                'woocommerce_thankyou_order_key',
                empty($_GET['key']) ? '' : wc_clean(filter_input(INPUT_GET, 'key', FILTER_SANITIZE_SPECIAL_CHARS))// WPCS: input var ok, CSRF ok.
            );
            if ($order_id > 0) {
                $order = wc_get_order($order_id);

                if (!is_a($order, 'WC_Order')) {
                    return $title;
                }

                $order_key_db = $order->get_order_key();

                if ($order_key_db !== $order_key) {
                    $order = false;
                }
            }

            if ($order === false) {
                return $title;
            }

            $order_payment_method = $order->get_payment_method();

            // Invalid gateway
            if ($this->id !== $order_payment_method) {
                return $title;
            }

            // Title for cancelled orders
            if ($order->has_status('cancelled')) {
                return __(
                    'Order cancelled',
                    'mollie-payments-for-woocommerce'
                );
            }

            // Checks and title for pending/open orders
            $payment = $this->paymentObject()->getActiveMolliePayment(
                $order->get_id()
            );

            // Mollie payment not found or invalid gateway
            if (!$payment || $payment->method !== $this->paymentMethod->getProperty('id')) {
                return $title;
            }

            if ($payment->isOpen()) {
                // Add a message to log and order explaining a payment with status "open", only if it hasn't been added already
                if ($order->get_meta('_mollie_open_status_note') !== '1') {
                    // Get payment method title
                    $payment_method_title = $this->method_title;

                    // Add message to log
                    $this->logger->debug(
                        $this->id
                        . ': Customer returned to store, but payment still pending for order #'
                        . $order_id
                        . '. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.'
                    );

                    // Add message to order as order note
                    $order->add_order_note(
                        sprintf(
                        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                            __(
                                '%1$s payment still pending (%2$s) but customer already returned to the store. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.',
                                'mollie-payments-for-woocommerce'
                            ),
                            $payment_method_title,
                            $payment->id . ($payment->mode === 'test' ? (' - '
                                . __(
                                    'test mode',
                                    'mollie-payments-for-woocommerce'
                                )) : '')
                        )
                    );
                    $order->update_meta_data('_mollie_open_status_note', '1');
                    $order->save();
                }

                // Update the title on the Order received page to better communicate that the payment is pending.
                $title .= __(
                    ', payment pending.',
                    'mollie-payments-for-woocommerce'
                );

                return $title;
            }
        }

        return $title;
    }

    /**
     * @param          $text
     * @param WC_Order| null $order
     *
     * @return string|void
     */
    public function onOrderReceivedText($text, $order)
    {
        if (!is_a($order, 'WC_Order')) {
            return $text;
        }

        $order_payment_method = $order->get_payment_method();

        // Invalid gateway
        if ($this->id !== $order_payment_method) {
            return $text;
        }

        if ($order->has_status('cancelled')) {
            return __(
                'Your order has been cancelled.',
                'mollie-payments-for-woocommerce'
            );
        }

        return $text;
    }

    /**
     * @return string|NULL
     */
    public function getSelectedIssuer(): ?string
    {
        $issuer_id = $this->pluginId . '_issuer_' . $this->id;
        //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $postedIssuer = wc_clean(wp_unslash($_POST[$issuer_id] ?? ''));
        return !empty($postedIssuer) ? $postedIssuer : null;
    }

    /**
     * Get the transaction URL.
     *
     * @param WC_Order $order
     *
     * @return string
     */
    public function get_transaction_url($order): string
    {
        $isPaymentApi = substr($order->get_meta('_mollie_order_id', true), 0, 3) === 'tr_'  ;
        $resource = ($order->get_meta('_mollie_order_id', true) && !$isPaymentApi) ? 'orders' : 'payments';

        $this->view_transaction_url = 'https://my.mollie.com/dashboard/'
            . $resource . '/%s?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner';

        return parent::get_transaction_url($order);
    }

    protected function gatewayHasFields(): void
    {
        if ($this->paymentMethod->getProperty('paymentFields')) {
            $this->has_fields = true;
        }

        /* Override show issuers dropdown? */
        $dropdownDisabled = $this->paymentMethod->hasProperty('issuers_dropdown_shown')
            && $this->paymentMethod->getProperty('issuers_dropdown_shown')
            === 'no';
        if ($dropdownDisabled) {
            $this->has_fields = false;
        }
    }

    /**
     * Get the correct currency for this payment or order
     * On order-pay page, order is already created and has an order currency
     * On checkout, order is not created, use get_woocommerce_currency
     *
     * @return string
     */
    public function getCurrencyFromOrder()
    {
        global $wp;
        if (!empty($wp->query_vars['order-pay'])) {
            $order_id = $wp->query_vars['order-pay'];
            $order = wc_get_order($order_id);

            $currency = $this->dataService->getOrderCurrency($order);
        } else {
            $currency = get_woocommerce_currency();
        }
        return $currency;
    }

    /**
     * Retrieve the customer's billing country
     * or fallback to the shop country
     *
     * @return mixed|void|null
     */
    public function getBillingCountry()
    {
        $customerExistsAndHasCountry = WC()->customer && !empty(WC()->customer->get_billing_country());
        $fallbackToShopCountry = wc_get_base_location()['country'];
        $billingCountry = $customerExistsAndHasCountry ? WC()->customer->get_billing_country() : $fallbackToShopCountry;

        return apply_filters(
            $this->pluginId
            . '_is_available_billing_country_for_payment_gateways',
            $billingCountry
        );
    }

    /**
     * Check the 'allowed_countries' setting
     * and return false if $billingCountry is in the list of not allowed.
     *
     * @param string $billingCountry
     * @param bool $status
     * @return bool
     */
    protected function isAllowedBillingCountry($billingCountry, $status)
    {
        $allowedCountries = $this->paymentMethod->getProperty('allowed_countries');
        //if no country is selected then this does not apply
        $bCountryIsAllowed = empty($allowedCountries)
            || in_array(
                $billingCountry,
                $allowedCountries,
                true
            );
        if (!$bCountryIsAllowed) {
            $status = false;
        }
        return $status;
    }

    /**
     * In WooCommerce check if the gateway is available for use (WooCommerce settings)
     * but also check if is not direct debit as this should not be shown in checkout
     *
     * @return bool
     */
    protected function checkEnabledNorDirectDebit(): bool
    {
        if ($this->enabled !== 'yes') {
            return false;
        }
        if ($this->id === SharedDataDictionary::DIRECTDEBIT) {
            return false;
        }
        return true;
    }

    /**
     * Check if the cart amount is available and > 0
     *
     * @return bool
     */
    protected function cartAmountAvailable()
    {
        return WC()->cart && $this->get_order_total() > 0;
    }
}
