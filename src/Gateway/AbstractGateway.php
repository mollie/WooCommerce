<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use InvalidArgumentException;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;
use UnexpectedValueException;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

abstract class AbstractGateway extends WC_Payment_Gateway
{
    /**
     * WooCommerce default statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD = 'on-hold';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled'; // Mollie uses canceled (US English spelling), WooCommerce and this plugin use cancelled.
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    const PAYMENT_METHOD_TYPE_PAYMENT = 'payment';
    const PAYMENT_METHOD_TYPE_ORDER = 'order';

    /**
     * @var string
     */
    protected $default_title;

    /**
     * @var string
     */
    protected $default_description;

    /**
     * @var bool
     */
    protected $display_logo;

    /**
     * Recurring total, zero does not define a recurring total
     *
     * @var int
     */
    public $recurring_totals = 0;

    /**
     * @var bool
     */
    public static $alreadyDisplayedInstructions = false;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var NoticeInterface
     */
    protected $notice;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * @var SurchargeService
     */
    protected $surchargeService;

    /**
     * @var MollieOrderService
     */
    protected $mollieOrderService;
    /**
     * @var HttpResponse
     */
    protected $httpResponse;
    /**
     * @var string
     */
    protected $pluginUrl;
    /**
     * @var string
     */
    protected $pluginPath;

    /**
     *
     */
    public function __construct(
        IconFactory $iconFactory,
        PaymentService $paymentService,
        SurchargeService $surchargeService,
        MollieOrderService $mollieOrderService,
        Logger $logger,
        NoticeInterface $notice,
        HttpResponse $httpResponse,
        string $pluginUrl,
        string $pluginPath
    ) {

        $this->logger = $logger;
        $this->notice = $notice;
        $this->iconFactory = $iconFactory;
        $this->paymentService = $paymentService;
        $this->surchargeService = $surchargeService;
        $this->mollieOrderService = $mollieOrderService;
        $this->httpResponse = $httpResponse;
        $this->pluginUrl = $pluginUrl;
        $this->pluginPath = $pluginPath;

        // No plugin id, gateway id is unique enough
        $this->plugin_id = '';
        // Use gateway class name as gateway id
        $this->gatewayId();
        // Set gateway title (visible in admin)
        $this->method_title = 'Mollie - ' . $this->getDefaultTitle();
        $this->method_description = $this->getSettingsDescription();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->display_logo = $this->get_option('display_logo') == 'yes';

        $this->initDescription();
        $this->initIcon();

        if (!has_action('woocommerce_thankyou_' . $this->id)) {
            add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        }
        $this->mollieOrderService->setGateway($this);

        add_action('woocommerce_api_' . $this->id, [$this->mollieOrderService, 'onWebhookAction']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_email_after_order_table', [$this, 'displayInstructions'], 10, 3);

        // Adjust title and text on Order Received page in some cases, see issue #166
        add_filter('the_title', [ $this, 'onOrderReceivedTitle' ], 10, 2);
        add_filter('woocommerce_thankyou_order_received_text', [ $this, 'onOrderReceivedText'], 10, 2);

        /* Override show issuers dropdown? */
        if ($this->get_option('issuers_dropdown_shown', 'yes') == 'no') {
            $this->has_fields = false;
        }

        if (!$this->isValidForUse()) {
            // Disable gateway if it's not valid for use
            $this->enabled = false;
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $settingsHelper = Plugin::getSettingsHelper();
        $this->form_fields = $settingsHelper->gatewayFormFields(
            $this->getDefaultTitle(),
            $this->getDefaultDescription(),
            $this->paymentConfirmationAfterCoupleOfDays()
        );
    }

    public function init_settings()
    {
        parent::init_settings();
    }
    /**
     * Save settings
     *
     * @since 1.0
     */
    /**
     * Save options in admin.
     */
    public function process_admin_options()
    {
        if (isset($_POST['save'])) {
            $this->iconFactory->processAdminOptionCustomLogo();
            $this->surchargeService->processAdminOptionSurcharge($this);
            //only credit cards have a selector
            if ($this->id == 'mollie_wc_gateway_creditcard') {
                $this->processAdminOptionCreditcardSelector();
            }
        }
        parent::process_admin_options();
    }

    protected function initIcon()
    {
        $this->iconFactory->initIcon($this, $this->display_logo, $this->pluginUrl);
    }

    public function get_icon()
    {
        $output = $this->icon ?: '';
        return apply_filters('woocommerce_gateway_icon', $output, $this->id);
    }

    public function getIconUrl(): string
    {
        return $this->iconFactory->getIconUrl($this->getMollieMethodId(), $this->pluginUrl);
    }

    protected function initDescription()
    {
        $description = $this->surchargeService->buildDescriptionWithSurcharge($this);

        $this->description = $description;
    }

    public function admin_options()
    {
        if (!$this->enabled && count($this->errors)) {
            echo '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'mollie-payments-for-woocommerce') . '</strong>: '
                    . implode('<br/>', $this->errors)
                    . '</p></div>';

            return;
        }

        $html = '';
        foreach ($this->get_form_fields() as $k => $v) {
            $type = $this->get_field_type($v);

            if ($type === 'multi_select_countries') {
                $html .= $this->multiSelectCountry();
            } else {
                if (method_exists($this, 'generate_' . $type . '_html')) {
                    $html .= $this->{'generate_' . $type . '_html'}($k, $v);
                } else {
                    $html .= $this->generate_text_html($k, $v);
                }
            }
        }

        echo '<h2>' . esc_html($this->get_method_title());
        wc_back_link(__('Return to payments', 'mollie-payments-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
        echo '</h2>';
        echo wp_kses_post(wpautop($this->get_method_description()));
        echo '<table class="form-table">'
                .
                $html
                .
                '</table>';
    }

    public function multiSelectCountry()
    {
        $selections = (array)$this->get_option('allowed_countries', []);
        $gatewayId = $this->getMollieMethodId();
        $id = 'mollie_wc_gateway_'.$gatewayId.'_allowed_countries';
        $title = __('Sell to specific countries', 'mollie-payments-for-woocommerce');
        $description = '<span class="description">' . wp_kses_post($this->get_option('description', '')) . '</span>';
        $countries = WC()->countries->countries;
        asort($countries);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?> </label>
            </th>
            <td class="forminp">
                <select multiple="multiple" name="<?php echo esc_attr($id); ?>[]" style="width:350px"
                        data-placeholder="<?php esc_attr_e('Choose countries&hellip;', 'mollie-payments-for-woocommerce'); ?>"
                        aria-label="<?php esc_attr_e('Country', 'mollie-payments-for-woocommerce'); ?>" class="wc-enhanced-select">
                    <?php
                    if (!empty($countries)) {
                        foreach ($countries as $key => $val) {
                            echo '<option value="' . esc_attr($key) . '"' . wc_selected($key, $selections) . '>' . esc_html($val) . '</option>';
                        }
                    }
                    ?>
                </select> <?php echo ($description) ? $description : ''; ?> <br/><a class="select_all button"
                                                                                    href="#"><?php esc_html_e('Select all', 'mollie-payments-for-woocommerce'); ?></a>
                <a class="select_none button" href="#"><?php esc_html_e('Select none', 'mollie-payments-for-woocommerce'); ?></a>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Validates the multiselect country field.
     * Overrides the one called by get_field_value() on WooCommerce abstract-wc-settings-api.php
     *
     * @param $key
     * @param $value
     * @return array|string
     */
    public function validate_multi_select_countries_field($key, $value)
    {
        return is_array($value) ? array_map('wc_clean', array_map('stripslashes', $value)) : '';
    }

    /**
     * Check if this gateway can be used
     *
     * @return bool
     */
    protected function isValidForUse()
    {
        //
        // Abort if this is not the WooCommerce settings page
        //

        // Return if function get_current_screen() is not defined
        if (! function_exists('get_current_screen')) {
            return true;
        }

        // Try getting get_current_screen()
        $current_screen = get_current_screen();

        // Return if get_current_screen() isn't set
        if (! $current_screen) {
            return true;
        }

        // Remove old MisterCash (only) from WooCommerce Payment settings
        if (is_admin() && ! empty($current_screen->base) && $current_screen->base == 'woocommerce_page_wc-settings') {
            $settings = Plugin::getSettingsHelper();

            if (! $this->isValidApiKeyProvided()) {
                $test_mode = $settings->isTestModeEnabled();

                $this->errors[] = ( $test_mode ? __('Test mode enabled.', 'mollie-payments-for-woocommerce') . ' ' : '' ) . sprintf(
                    /* translators: The surrounding %s's Will be replaced by a link to the global setting page */
                    __('No API key provided. Please %1$sset you Mollie API key%2$s first.', 'mollie-payments-for-woocommerce'),
                    '<a href="' . $settings->getGlobalSettingsUrl() . '">',
                    '</a>'
                );

                return false;
            }

            // This should be simpler, check for specific payment method in settings, not on all pages
            if (null === $this->getMollieMethod()) {
                $this->errors[] = sprintf(
                /* translators: Placeholder 1: payment method title. The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('%1$s not enabled in your Mollie profile. You can enable it by editing your %2$sMollie profile%3$s.', 'mollie-payments-for-woocommerce'),
                    $this->getDefaultTitle(),
                    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                    '</a>'
                );

                return false;
            }

            if (! $this->isCurrencySupported()) {
                $this->errors[] = sprintf(
                /* translators: Placeholder 1: WooCommerce currency, placeholder 2: Supported Mollie currencies */
                    __('Current shop currency %1$s not supported by Mollie. Read more about %2$ssupported currencies and payment methods.%3$s ', 'mollie-payments-for-woocommerce'),
                    get_woocommerce_currency(),
                    '<a href="https://help.mollie.com/hc/en-us/articles/360003980013-Which-currencies-are-supported-and-what-is-the-settlement-currency-" target="_blank">',
                    '</a>'
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Check if the gateway is available for use
     *
     * @return bool
     */
    public function is_available()
    {
        // In WooCommerce check if the gateway is available for use (WooCommerce settings)
        if ($this->enabled != 'yes') {
            return false;
        }

        // Only in WooCommerce checkout, check min/max amounts
        if (WC()->cart && $this->get_order_total() > 0) {
            // Check the current (normal) order total
            $order_total = $this->get_order_total();

            // Get the correct currency for this payment or order
            // On order-pay page, order is already created and has an order currency
            // On checkout, order is not created, use get_woocommerce_currency
            global $wp;
            if (! empty($wp->query_vars['order-pay'])) {
                $order_id = $wp->query_vars['order-pay'];
                $order = wc_get_order($order_id);

                $currency = Plugin::getDataHelper()->getOrderCurrency($order);
            } else {
                $currency = get_woocommerce_currency();
            }

            $billing_country = WC()->customer->get_billing_country();
            $billing_country = apply_filters(
                Plugin::PLUGIN_ID . '_is_available_billing_country_for_payment_gateways',
                $billing_country
            );

            // Get current locale for this user
            $payment_locale = Plugin::getSettingsHelper()->getPaymentLocale();

            try {
                $filters = $this->getFilters(
                    $currency,
                    $order_total,
                    $payment_locale,
                    $billing_country
                );
            } catch (InvalidArgumentException $exception) {
                $this->logger->log(\WC_Log_Levels::DEBUG, $exception->getMessage());
                return false;
            }

            // For regular payments, check available payment methods, but ignore SSD gateway (not shown in checkout)
            $status = ($this->id !== 'mollie_wc_gateway_directdebit') ? $this->isAvailableMethodInCheckout($filters) : false;
            $allowedCountries = $this->get_option('allowed_countries', []);
            //if no country is selected then this does not apply
            $bCountryIsAllowed = empty($allowedCountries) ? true : in_array($billing_country, $allowedCountries);
            if (!$bCountryIsAllowed) {
                $status = false;
            }
            // Do extra checks if WooCommerce Subscriptions is installed
            if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin')) {
                // Check recurring totals against recurring payment methods for future renewal payments
                $recurring_totals = $this->get_recurring_total();

                // See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
                $accept_manual_renewals = ( 'yes' == get_option(\WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals', 'no') ) ? true : false;
                $supports_subscriptions = $this->supports('subscriptions');

                if ($accept_manual_renewals !== true && $supports_subscriptions) {
                    if (! empty($recurring_totals)) {
                        foreach ($recurring_totals as $recurring_total) {
                            // First check recurring payment methods CC and SDD
                            $filters =  [
                                'amount' =>  [
                                    'currency' => $currency,
                                    'value' => Plugin::getDataHelper()->formatCurrencyValue($recurring_total, $currency),
                                ],
                                'resource' => 'orders',
                                'billingCountry' => $billing_country,
                                'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_RECURRING,
                            ];

                            $payment_locale and $filters['locale'] = $payment_locale;
                        }
                        $status = $this->isAvailableMethodInCheckout($filters);
                        // Check available first payment methods with today's order total, but ignore SSD gateway (not shown in checkout)
                        if ($this->id !== 'mollie_wc_gateway_directdebit') {
                            $filters =  [
                                'amount' =>  [
                                    'currency' => $currency,
                                    'value' => Plugin::getDataHelper()->formatCurrencyValue($order_total, $currency),
                                ],
                                'resource' => 'orders',
                                'locale' => $payment_locale,
                                'billingCountry' => $billing_country,
                                'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_FIRST,
                            ];

                            $status = $this->isAvailableMethodInCheckout($filters);
                        }
                    }
                }
            }

            return $status;
        }

        return true;
    }

    /**
     * Will the payment confirmation be delivered after a couple of days.
     *
     * Overwrite this method for payment gateways where the payment confirmation takes a couple of days.
     * When this method return true, a new setting will be available where the merchant can set the initial
     * payment state: on-hold or pending
     *
     * @return bool
     */
    protected function paymentConfirmationAfterCoupleOfDays()
    {
        return false;
    }

    /**
     * @param int $order_id
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return array
     */
    public function process_payment($order_id)
    {
        $this->paymentService->setGateway($this);
        return $this->paymentService->processPayment($order_id, $this->paymentConfirmationAfterCoupleOfDays());
    }

    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order                                            $order
     * @param MollieOrder|MolliePayment $payment_object
     *
     * @return string
     */
    public function getProcessPaymentRedirect(WC_Order $order, $payment_object)
    {
        /*
         * Redirect to payment URL
         */
        return $payment_object->getCheckoutUrl();
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

        $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $this->id . ": Order $order_id does not need a payment by Mollie (payment {$payment->id}).", [true]);
    }

    /**
     * @param WC_Order $order
     *
     * @return string
     */
    public function getReturnRedirectUrlForOrder(WC_Order $order)
    {
        $order_id = $order->get_id();
        $debugLine = __METHOD__ . " {$order_id}: Determine what the redirect URL in WooCommerce should be.";
        $this->logger->log(\WC_Log_Levels::DEBUG, $debugLine);
        $hookReturnPaymentStatus = 'success';
        $returnRedirect = $this->get_return_url($order);
        $failedRedirect = $order->get_checkout_payment_url(false);
        if ($this->orderNeedsPayment($order)) {
            $hasCancelledMolliePayment = $this->paymentObject()->getCancelledMolliePaymentId($order_id);

            if ($hasCancelledMolliePayment) {
                $settings_helper = Plugin::getSettingsHelper();
                $order_status_cancelled_payments = $settings_helper->getOrderStatusCancelledPayments();

                // If user set all cancelled payments to also cancel the order,
                // redirect to /checkout/order-received/ with a message about the
                // order being cancelled. Otherwise redirect to /checkout/order-pay/ so
                // customers can try to pay with another payment method.
                if ($order_status_cancelled_payments == 'cancelled') {
                    return $this->get_return_url($order);
                } else {
                    $this->notice->addNotice(
                        'notice',
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
                if (! $payment->isOpen() && ! $payment->isPending() && ! $payment->isPaid() && ! $payment->isAuthorized()) {
                    $this->notice->addNotice(
                        'notice',
                        __(
                            'Your payment was not successful. Please complete your order with a different payment method.',
                            'mollie-payments-for-woocommerce'
                        )
                    );
                    // Return to order payment page
                    return $failedRedirect;
                }
                if ($payment->method === "giftcard") {
                    $this->debugGiftcardDetails($payment, $order);
                }
            } catch (UnexpectedValueException $exc) {
                $this->notice->addNotice(
                    'notice',
                    __(
                        'Your payment was not successful. Please complete your order with a different payment method.',
                        'mollie-payments-for-woocommerce'
                    )
                );
                $exceptionMessage = $exc->getMessage();
                $debugLine = __METHOD__ . " Problem processing the payment. {$exceptionMessage}";
                $this->logger->log(\WC_Log_Levels::DEBUG, $debugLine);
                $hookReturnPaymentStatus = 'failed';
            }
        }
        do_action(Plugin::PLUGIN_ID . '_customer_return_payment_' . $hookReturnPaymentStatus, $order);

        /*
         * Return to order received page
         */
        return $this->get_return_url($order);
    }
    /**
     * Retrieve the payment object
     *
     * @return Mollie_WC_Payment_Object
     */
    protected function paymentObject()
    {
        return Plugin::getPaymentObject();
    }

    /**
     * Retrieve the active payment object
     *
     * @param $orderId
     * @param $useCache
     * @return Payment
     * @throws UnexpectedValueException
     */
    protected function activePaymentObject($orderId, $useCache)
    {
        $paymentObject = $this->paymentObject();
        $activePaymentObject = $paymentObject->getActiveMolliePayment($orderId, $useCache);

        if ($activePaymentObject === null) {
            throw new UnexpectedValueException(
                "Active Payment Object is not a valid Payment Resource instance. Order ID: {$orderId}"
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
        if (! $order) {
            $error_message = "Could not find WooCommerce order $order_id.";

            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $error_message);

            return new WP_Error('1', $error_message);
        }

        // Check if there is a Mollie Payment Order object connected to this WooCommerce order
        $payment_object_id = Plugin::getPaymentObject()->getActiveMollieOrderId($order_id);

        // If there is no Mollie Payment Order object, try getting a Mollie Payment Payment object
        if ($payment_object_id == null) {
            $payment_object_id = Plugin::getPaymentObject()->getActiveMolliePaymentId($order_id);
        }

        // Mollie Payment object not found
        if (! $payment_object_id) {
            $error_message = "Can\'t process refund. Could not find Mollie Payment object id for order $order_id.";

            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $error_message);

            return new WP_Error('1', $error_message);
        }

        try {
            $payment_object = Plugin::getPaymentFactoryHelper()->getPaymentObject(
                $payment_object_id
            );
        } catch (ApiException $exception) {
            $exceptionMessage = $exception->getMessage();
            $this->logger->log(\WC_Log_Levels::DEBUG, $exceptionMessage);
            return new WP_Error('error', $exceptionMessage);
        }

        if (! $payment_object) {
            $error_message = "Can\'t process refund. Could not find Mollie Payment object data for order $order_id.";

            $this->logger->log(\WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $error_message);

            return new WP_Error('1', $error_message);
        }

        return $payment_object->refund($order, $order_id, $payment_object, $amount, $reason);
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
        $this->displayInstructions($order, $admin_instructions = false, $plain_text = false);
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
    public function displayInstructions(WC_Order $order, $admin_instructions = false, $plain_text = false)
    {
        if (! self::$alreadyDisplayedInstructions) {
            $order_payment_method = $order->get_payment_method();

            // Invalid gateway
            if ($this->id !== $order_payment_method) {
                return;
            }

            $payment = Plugin::getPaymentObject()->getActiveMolliePayment($order->get_id());

            // Mollie payment not found or invalid gateway
            if (! $payment || $payment->method != $this->getMollieMethodId()) {
                return;
            }

            $instructions = $this->getInstructions($order, $payment, $admin_instructions, $plain_text);

            if (! empty($instructions)) {
                $instructions = wptexturize($instructions);

                if ($plain_text) {
                    echo $instructions . PHP_EOL;
                } else {
                    echo '<section class="woocommerce-order-details woocommerce-info mollie-instructions" >';
                    echo wpautop($instructions) . PHP_EOL;
                    echo '</section>';
                }
            }
        }
        self::$alreadyDisplayedInstructions = true;
    }

    /**
     * @param WC_Order                  $order
     * @param Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions(WC_Order $order, Payment $payment, $admin_instructions, $plain_text)
    {
        // No definite payment status
        if ($payment->isOpen() || $payment->isPending()) {
            if ($admin_instructions) {
                // Message to admin
                return __('We have not received a definite payment status.', 'mollie-payments-for-woocommerce');
            } else {
                // Message to customer
                return __('We have not received a definite payment status. You will receive an email as soon as we receive a confirmation of the bank/merchant.', 'mollie-payments-for-woocommerce');
            }
        } elseif ($payment->isPaid()) {
            return sprintf(
            /* translators: Placeholder 1: payment method */
                __('Payment completed with <strong>%s</strong>', 'mollie-payments-for-woocommerce'),
                $this->get_title()
            );
        }

        return null;
    }

    /**
     * @param WC_Order $order
     */
    public function onOrderReceivedTitle($title, $id = null)
    {
        if (is_order_received_page() && get_the_ID() === $id) {
            global $wp;

            $order = false;
            $order_id = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
            $order_key = apply_filters('woocommerce_thankyou_order_key', empty($_GET['key']) ? '' : wc_clean($_GET['key']));
            if ($order_id > 0) {
                $order = wc_get_order($order_id);

                if (! is_a($order, 'WC_Order')) {
                    return $title;
                }

                $order_key_db = $order->get_order_key();

                if ($order_key_db != $order_key) {
                    $order = false;
                }
            }

            if ($order == false) {
                return $title;
            }

            $order_payment_method = $order->get_payment_method();

            // Invalid gateway
            if ($this->id !== $order_payment_method) {
                return $title;
            }

            // Title for cancelled orders
            if ($order->has_status('cancelled')) {
                $title = __('Order cancelled', 'mollie-payments-for-woocommerce');

                return $title;
            }

            // Checks and title for pending/open orders
            $payment = Plugin::getPaymentObject()->getActiveMolliePayment($order->get_id());

            // Mollie payment not found or invalid gateway
            if (! $payment || $payment->method != $this->getMollieMethodId()) {
                return $title;
            }

            if ($payment->isOpen()) {
                // Add a message to log and order explaining a payment with status "open", only if it hasn't been added already
                if (get_post_meta($order_id, '_mollie_open_status_note', true) !== '1') {
                    // Get payment method title
                    $payment_method_title = $this->method_title;

                    // Add message to log
                    $this->logger->log(
                        \WC_Log_Levels::DEBUG,
                        $this->id
                                       . ': Customer returned to store, but payment still pending for order #'
                                       . $order_id
                                       . '. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.'
                    );

                    // Add message to order as order note
                    $order->add_order_note(sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                        __('%1$s payment still pending (%2$s) but customer already returned to the store. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.', 'mollie-payments-for-woocommerce'),
                        $payment_method_title,
                        $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
                    ));

                    update_post_meta($order_id, '_mollie_open_status_note', '1');
                }

                // Update the title on the Order received page to better communicate that the payment is pending.
                $title .= __(', payment pending.', 'mollie-payments-for-woocommerce');

                return $title;
            }
        }

        return $title;
    }

    /**
     * @param WC_Order $order
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
            $text = __('Your order has been cancelled.', 'mollie-payments-for-woocommerce');

            return $text;
        }

        return $text;
    }

    /**
     * @return \Mollie\Api\Resources\Method|null
     */
    public function getMollieMethod()
    {
        $test_mode = Plugin::getSettingsHelper()->isTestModeEnabled();

        return Plugin::getDataHelper()->getPaymentMethod(
            $this->getMollieMethodId(),
            $test_mode
        );
    }

    /**
     * @return string
     */
    public function getInitialOrderStatus()
    {
        if ($this->paymentConfirmationAfterCoupleOfDays()) {
            return $this->get_option('initial_order_status');
        }

        return self::STATUS_PENDING;
    }

    /**
     * Get the url to return to on Mollie return
     * saves the return redirect and failed redirect, so we save the page language in case there is one set
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with order id and key as params
     */
    public function getReturnUrl(WC_Order $order)
    {
        $returnUrl = $this->get_return_url($order);
        $returnUrl = untrailingslashit($returnUrl);
        $returnUrl = $this->asciiDomainName($returnUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $onMollieReturn = 'onMollieReturn';
        $returnUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $returnUrl,
            $onMollieReturn
        );
        $returnUrl = untrailingslashit($returnUrl);

        $this->logger->log(\WC_Log_Levels::DEBUG, "{$this->id} : Order {$orderId} returnUrl: {$returnUrl}", [true]);

        return apply_filters(Plugin::PLUGIN_ID . '_return_url', $returnUrl, $order);
    }

    /**
     * Get the webhook url
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with gateway and order id and key as params
     */
    public function getWebhookUrl(WC_Order $order)
    {
        $webhookUrl = WC()->api_request_url($this->gatewayId());
        $webhookUrl = untrailingslashit($webhookUrl);
        $webhookUrl = $this->asciiDomainName($webhookUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $webhookUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $webhookUrl
        );
        $webhookUrl = untrailingslashit($webhookUrl);

        $this->logger->log(\WC_Log_Levels::DEBUG, "{$this->id} : Order {$orderId} webhookUrl: {$webhookUrl}", [true]);

        return apply_filters(Plugin::PLUGIN_ID . '_webhook_url', $webhookUrl, $order);
    }

    /**
     * @return string|NULL
     */
    public function getSelectedIssuer()
    {
        $issuer_id = Plugin::PLUGIN_ID . '_issuer_' . $this->id;

        return !empty($_POST[$issuer_id]) ? $_POST[$issuer_id] : null;
    }

    /**
     * @return array
     */
    protected function getSupportedCurrencies()
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

        return apply_filters('woocommerce_' . $this->id . '_supported_currencies', $default);
    }

    /**
     * @return bool
     */
    protected function isCurrencySupported()
    {
        return in_array(get_woocommerce_currency(), $this->getSupportedCurrencies());
    }

    /**
     * @return bool
     */
    protected function isValidApiKeyProvided()
    {
        $settings = Plugin::getSettingsHelper();
        $test_mode = $settings->isTestModeEnabled();
        $api_key = $settings->getApiKey($test_mode);

        return !empty($api_key) && preg_match('/^(live|test)_\w{30,}$/', $api_key);
    }

    /**
     * @return mixed
     */
    abstract public function getMollieMethodId();

    /**
     * @return string
     */
    abstract public function getDefaultTitle();

    /**
     * @return string
     */
    abstract protected function getSettingsDescription();

    /**
     * @return string
     */
    abstract protected function getDefaultDescription();

    /**
     * @return mixed
     */
    protected function get_recurring_total()
    {
        if (isset(WC()->cart)) {
            if (! empty(WC()->cart->recurring_carts)) {
                $this->recurring_totals =  []; // Reset for cached carts

                foreach (WC()->cart->recurring_carts as $cart) {
                    if (! $cart->prices_include_tax) {
                        $this->recurring_totals[] = $cart->cart_contents_total;
                    } else {
                        $this->recurring_totals[] = $cart->cart_contents_total + $cart->tax_total;
                    }
                }
            } else {
                return false;
            }
        }

        return $this->recurring_totals;
    }

    /**
     * Check if payment method is available in checkout based on amount, currency and sequenceType
     *
     * @param $filters
     *
     * @return bool
     */
    protected function isAvailableMethodInCheckout($filters)
    {
        $settings_helper = Plugin::getSettingsHelper();
        $test_mode = $settings_helper->isTestModeEnabled();

        $data_helper = Plugin::getDataHelper();
        $methods = $data_helper->getApiPaymentMethods($test_mode, $use_cache = true, $filters);

        // Get the ID of the WooCommerce/Mollie payment method
        $woocommerce_method = $this->getMollieMethodId();

        // Set all other payment methods to false, so they can be updated if available
        foreach ($methods as $method) {
            if ($method['id'] == $woocommerce_method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the transaction URL.
     *
     * @param  WC_Order $order
     *
     * @return string
     */
    public function get_transaction_url($order)
    {
        $resource = ($order->get_meta('_mollie_order_id', true)) ? 'orders' : 'payments';

        $this->view_transaction_url = 'https://www.mollie.com/dashboard/' . $resource . '/%s';

        return parent::get_transaction_url($order);
    }

    /**
     * @param $order_id
     * @param $order_key
     * @param $webhook_url
     * @param string $filterFlag
     *
     * @return string
     */
    protected function appendOrderArgumentsToUrl($order_id, $order_key, $webhook_url, $filterFlag = '')
    {
        $webhook_url = add_query_arg(
            [
                        'order_id' => $order_id,
                        'key' => $order_key,
                        'filter_flag' => $filterFlag,
                ],
            $webhook_url
        );
        return $webhook_url;
    }

    /**
     * Method to print the giftcard payment details on debug and order note
     *
     * @param Mollie\Api\Resources\Payment $payment
     * @param WC_Order                     $order
     *
     */
    protected function debugGiftcardDetails(
        Mollie\Api\Resources\Payment $payment,
        WC_Order $order
    ) {

        $details = $payment->details;
        if (!$details) {
            return;
        }
        $orderNoteLine = "";
        foreach ($details->giftcards as $giftcard) {
            $orderNoteLine .= sprintf(
                esc_html_x(
                    'Mollie - Giftcard details: %1$s %2$s %3$s.',
                    'Placeholder 1: giftcard issuer, Placeholder 2: amount value, Placeholder 3: currency',
                    'mollie-payments-for-woocommerce'
                ),
                $giftcard->issuer,
                $giftcard->amount->value,
                $giftcard->amount->currency
            );
        }
        if ($details->remainderMethod) {
            $orderNoteLine .= sprintf(
                esc_html_x(
                    ' Remainder: %1$s %2$s %3$s.',
                    'Placeholder 1: remainder method, Placeholder 2: amount value, Placeholder 3: currency',
                    'mollie-payments-for-woocommerce'
                ),
                $details->remainderMethod,
                $details->remainderAmount->value,
                $details->remainderAmount->currency
            );
        }

        $order->add_order_note($orderNoteLine);
    }

    /**
     * Returns a list of filters, ensuring that the values are valid.
     * @param $currency
     * @param $orderTotal
     * @param $paymentLocale
     * @param $billingCountry
     * @return array
     * @throws InvalidArgumentException
     */
    protected function getFilters($currency, $orderTotal, $paymentLocale, $billingCountry)
    {
        $amountValue = $this->getAmountValue($orderTotal, $currency);
        if ($amountValue <= 0) {
            throw new InvalidArgumentException(sprintf('Amount %s is not valid.', $amountValue));
        }

        // Check if currency is in ISO 4217 alpha-3 format (ex: EUR)
        if (!preg_match('/^[a-zA-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(sprintf('Currency %s is not valid.', $currency));
        }

        // Check if billing country is in ISO 3166-1 alpha-2 format (ex: NL)
        if (!preg_match('/^[a-zA-Z]{2}$/', $billingCountry)) {
            throw new InvalidArgumentException(sprintf('Billing Country %s is not valid.', $billingCountry));
        }

        return [
            'amount' => [
                'currency' => $currency,
                'value' => $amountValue,
            ],
            'locale' => $paymentLocale,
            'billingCountry' => $billingCountry,
            'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_ONEOFF,
            'resource' => 'orders',
        ];
    }

    /**
     * @param $order_total
     * @param $currency
     * @return int
     */
    protected function getAmountValue($order_total, $currency)
    {
        return Plugin::getDataHelper()->formatCurrencyValue(
            $order_total,
            $currency
        );
    }

    /**
     * @param $url
     * @return string
     */
    protected function asciiDomainName($url)
    {
        if (function_exists('idn_to_ascii')) {
            $parsed = parse_url($url);
            $query = $parsed['query'];
            $url = str_replace('?' . $query, '', $url);
            if (defined('IDNA_NONTRANSITIONAL_TO_ASCII') && defined('INTL_IDNA_VARIANT_UTS46')) {
                $url = idn_to_ascii($url, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46) ? idn_to_ascii(
                    $url,
                    IDNA_NONTRANSITIONAL_TO_ASCII,
                    INTL_IDNA_VARIANT_UTS46
                ) : $url;
            } else {
                $url = idn_to_ascii($url) ? idn_to_ascii($url) : $url;
            }
            $url = $url . '?' . $query;
        }

        return $url;
    }

    protected function gatewayId()
    {
        $fullGatewayClassname = get_class($this);
        preg_match('/\w*$/', $fullGatewayClassname, $matches);
        $this->id = strtolower($matches[0]);
        return $this->id;
    }
}
