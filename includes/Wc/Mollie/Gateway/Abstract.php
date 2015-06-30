<?php
abstract class WC_Mollie_Gateway_Abstract extends WC_Payment_Gateway
{
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
     *
     */
    public function __construct ()
    {
        $this->method_title = 'Mollie - ' . $this->getDefaultTitle();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->display_logo = $this->get_option('display_logo') == 'yes';

        $this->_initDescription();
        $this->_initIcon();

        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'webhookAction'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_after_order_table', array($this, 'displayInstructions'), 10, 3);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => sprintf(__('Enable %s', 'woocommerce-mollie-payments'), $this->getDefaultTitle()),
                'default'     => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => sprintf(__('This controls the title which the user sees during checkout. Default <code>%s</code>', 'woocommerce'), $this->getDefaultTitle()),
                'default'     => $this->getDefaultTitle(),
                'desc_tip'    => true,
            ),
            'display_logo' => array(
                'title'       => __('Display logo', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Display logo on checkout page. Default <code>enabled</code>', 'woocommerce'),
                'default'     => 'yes'
            ),
            'description' => array(
                'title'       => __( 'Description', 'woocommerce' ),
                'type'        => 'textarea',
                'description' => sprintf(__('Payment method description that the customer will see on your checkout. Default <code>%s</code>', 'woocommerce'), $this->getDefaultDescription()),
                'default'     => $this->getDefaultDescription(),
                'desc_tip'    => true,
            ),
        );
    }

    protected function _initIcon ()
    {
        if ($this->display_logo)
        {
            $payment_method = $this->getMollieMethod();

            if ($payment_method)
            {
                $this->icon = $payment_method->image->normal;
            }
        }
    }

    protected function _initDescription ()
    {
        $description = '';

        if (WC_Mollie::getSettingsHelper()->isTestModeEnabled())
        {
            $description .= '<strong>' . __('Test mode enabled.', 'woocommerce-mollie-payments') . '</strong><br/>';
        }

        $description .= $this->get_option('description');

        $this->description = $description;
    }

    public function admin_options ()
    {
        if (!$this->isValidForUse())
        {
            echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', 'woocommerce-mollie-payments' ) . '</strong>: '
                . implode('<br/>', $this->errors)
                . '</p></div>';

            return;
        }

        parent::admin_options();
    }

    /**
     * This method will check if this gateway can be used
     * @return bool
     */
    public function is_available()
    {
        if (!parent::is_available())
        {
            return false;
        }

        if (!$this->isValidForUse())
        {
            return false;
        }

        return true;
    }

    /**
     * Check if this gateway can be used
     *
     * @return bool
     */
    public function isValidForUse()
    {
        $settings = WC_Mollie::getSettingsHelper();

        if (!$this->isValidApiKeyProvided())
        {
            $test_mode = $settings->isTestModeEnabled();

            $this->errors[] = ($test_mode ? __('Test mode enabled.', 'woocommerce-mollie-payments') . ' ' : '') . sprintf(
                __('No API key provided. Please %sset you Mollie API key%s first.', 'woocommerce-mollie-payments'),
                '<a href="' . $settings->getGlobalSettingsUrl() . '">',
                '</a>'
            );

            return false;
        }

        if (null === $this->getMollieMethod())
        {
            $this->errors[] = sprintf(
                __('%s not enabled in your Mollie profile. You can enabled it by editing your %sMollie profile%s.', 'woocommerce-mollie-payments'),
                $this->getDefaultTitle(),
                '<a href="https://www.mollie.com/beheer/account/profielen/" target="_blank">',
                '</a>'
            );

            return false;
        }

        if (!$this->isCurrencySupported())
        {
            $this->errors[] = sprintf(
                __('Shop currency %s not supported by Mollie. Mollie only supports: %s.', 'woocommerce-mollie-payments'),
                get_woocommerce_currency(),
                implode(', ', $this->getSupportedCurrencies())
            );

            return false;
        }

        return true;
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function process_payment ($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order)
        {
            WC_Mollie::debug($this->id . ': Could not process payment, order ' . $order_id . ' not found.');

            WC_Mollie::addNotice(sprintf(__('Could not load order %s', 'woocommerce-mollie-payments'), $order_id), 'error');

            return array('result' => 'failure');
        }

        $initial_status = $this->getInitialStatus();

        // Overwrite plugin-wide
        $initial_status = apply_filters(WC_Mollie::PLUGIN_ID . '_initial_order_status', $initial_status);

        // Overwrite gateway-wide
        $initial_status = apply_filters(WC_Mollie::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_status);

        // Set initial status
        // Status is only updated if the new status is not the same as the default order status (pending)
        $order->update_status(
            $initial_status,
            __('Awaiting payment confirmation.', 'woocommerce-mollie-payments') . "\n"
        );

        $settings_helper     = WC_Mollie::getSettingsHelper();

        $payment_description = $settings_helper->getPaymentDescription();
        $payment_locale      = $settings_helper->getPaymentLocale();
        $mollie_method       = $this->getMollieMethodId();
        $selected_issuer     = $this->getSelectedIssuer();
        $return_url          = $this->getReturnUrl($order);
        $webhook_url         = $this->getWebhookUrl($order);

        $data = array_filter(array(
            'amount'          => $order->get_total(),
            'description'     => str_replace('%', $order->get_order_number(), $payment_description),
            'redirectUrl'     => $return_url,
            'webhookUrl'      => $webhook_url,
            'method'          => $mollie_method,
            'issuer'          => $selected_issuer,
            'locale'          => $payment_locale,
            'billingAddress'  => $order->billing_address_1,
            'billingCity'     => $order->billing_city,
            'billingRegion'   => $order->billing_state,
            'billingPostal'   => $order->billing_postcode,
            'billingCountry'  => $order->billing_country,
            'shippingAddress' => $order->shipping_address_1,
            'shippingCity'    => $order->shipping_city,
            'shippingRegion'  => $order->shipping_state,
            'shippingPostal'  => $order->shipping_postcode,
            'shippingCountry' => $order->shipping_country,
            'metadata'        => array(
                'order_id' => $order->id,
            ),
        ));

        $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $order);

        try
        {
            WC_Mollie::debug($this->id . ': Create payment for order ' . $order->id, true);

            do_action(WC_Mollie::PLUGIN_ID . '_create_payment', $data, $order);

            // Is test mode enabled?
            $test_mode = WC_Mollie::getSettingsHelper()->isTestModeEnabled();

            // Create Mollie payment
            $payment   = WC_Mollie::getApiHelper()->getApiClient($test_mode)->payments->create($data);

            // Set active Mollie payment
            WC_Mollie::getDataHelper()->setActiveMolliePayment($order->id, $payment);

            do_action(WC_Mollie::PLUGIN_ID . '_payment_created', $payment, $order);

            WC_Mollie::debug($this->id . ': Payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $order->id);

            $order->add_order_note(sprintf(
                __('%s payment started (%s).', 'woocommerce-mollie-payments'),
                $this->method_title,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'woocommerce-mollie-payments')) : '')
            ));

            return array(
                'result' => 'success',
                'redirect' => $payment->getPaymentUrl(),
            );
        }
        catch (Mollie_API_Exception $e)
        {
            WC_Mollie::debug($this->id . ': Failed to create payment for order ' . $order->id . ': ' . $e->getMessage());

            $message = sprintf(__('Could not create %s payment.', 'woocommerce-mollie-payments'), $this->title);

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                $message .= ' ' . $e->getMessage();
            }

            WC_Mollie::addNotice($message, 'error');
        }

        return array('result' => 'failure');
    }

    public function webhookAction ()
    {
        // Webhook test by Mollie
        if (isset($_GET['testByMollie']))
        {
            WC_Mollie::debug(__METHOD__ . ': Webhook tested by Mollie.', true);
            return;
        }

        if (empty($_GET['order_id']) || empty($_GET['key']))
        {
            // Invalid parameters
            header('Status: 400 Bad Request');

            WC_Mollie::debug(__METHOD__ . ":  No order ID or order key provided.");
            return;
        }

        $order_id = $_GET['order_id'];
        $key      = $_GET['key'];

        $order    = wc_get_order($order_id);

        if (!$order)
        {
            header("Status: 404 Not Found");
            WC_Mollie::debug(__METHOD__ . ":  Could not find order $order_id.");
            return;
        }

        if (!$order->key_is_valid($key))
        {
            header('Status: 401 Unauthorized');
            WC_Mollie::debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
            return;
        }

        // No Mollie payment id provided
        if (empty($_REQUEST['id']))
        {
            // Invalid parameters
            header('Status: 400 Bad Request');

            WC_Mollie::debug(__METHOD__ . ': No payment ID provided.', true);
            return;
        }

        $payment_id  = $_REQUEST['id'];
        $data_helper = WC_Mollie::getDataHelper();

        $test_mode = $data_helper->getActiveMolliePaymentMode($order_id) == 'test';

        // Load the payment from Mollie, do not use cache
        $payment = $data_helper->getPayment($payment_id, $test_mode, $use_cache = false);

        // Payment not found
        if (!$payment)
        {
            header('Status: 404 Not Found');

            WC_Mollie::debug(__METHOD__ . ": payment $payment_id not found.", true);
            return;
        }

        // TODO: remove!
        WC_Mollie::debug(__METHOD__ . ": Payment on webhook: " . print_r($payment, true));

        if ($order_id != $payment->metadata->order_id)
        {
            header('Status: 400 Bad Request');

            WC_Mollie::debug(__METHOD__ . ": Order ID does not match order_id in payment metadata. Payment ID {$payment->id}, order ID $order_id");
            return;
        }

        // Payment requires different gateway, payment method changed on Mollie platform?
        if ($payment->method != $this->getMollieMethodId())
        {
            // TODO: return 200 to ignore?
            header('Status: 400 Bad Request');

            WC_Mollie::debug($this->id . ": Invalid gateway. This gateways can process Mollie " . $this->getMollieMethodId() . " payments. This payment has payment method " . $payment->method, true);
            return;
        }

        // Order does not need a payment
        if (!$this->orderNeedsPayment($order))
        {
            // Duplicate webhook call
            header('Status: 204 No Content');

            WC_Mollie::debug($this->id . ": Order $order_id does not need a payment (payment webhook {$payment->id}).", true);
            return;
        }

        WC_Mollie::debug($this->id . ": Mollie payment {$payment->id} (" . $payment->mode . ") webhook call for order {$order->id}.", true);

        $method_name = 'onWebhook' . ucfirst($payment->status);

        if (method_exists($this, $method_name))
        {
            $this->{$method_name}($order, $payment);
        }
        else
        {
            $order->add_order_note(sprintf(
                __('%s payment %s (%s).', 'woocommerce-mollie-payments'),
                $this->method_title,
                $payment->status,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'woocommerce-mollie-payments')) : '')
            ));
        }

        // Status 200
    }

    /**
     * @param Wc_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookPaid (Wc_Order $order, Mollie_API_Object_Payment $payment)
    {
        WC_Mollie::debug(__METHOD__ . ' called.');

        // Woocommerce 2.2.0 has the option to store the Payment transaction id.
        $woo_version = get_option('woocommerce_version', 'Unknown');

        if (version_compare($woo_version, '2.2.0', '>='))
        {
            $order->payment_complete($payment->id);
        }
        else
        {
            $order->payment_complete();
        }

        $order->add_order_note(sprintf(
            __('Order completed using %s payment (%s).', 'woocommerce-mollie-payments'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'woocommerce-mollie-payments')) : '')
        ));
    }

    /**
     * @param Wc_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookCancelled (Wc_Order $order, Mollie_API_Object_Payment $payment)
    {
        WC_Mollie::debug(__METHOD__ . ' called.');

        // Unset active Mollie payment id
        WC_Mollie::getDataHelper()
            ->unsetActiveMolliePayment($order->id)
            ->setCancelledMolliePaymentId($order->id, $payment->id);

        // Reset state
        $order->update_status('pending');

        // User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
        $order->add_order_note(sprintf(
            __('%s payment cancelled (%s).', 'woocommerce-mollie-payments'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'woocommerce-mollie-payments')) : '')
        ));
    }

    /**
     * @param Wc_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookExpired (Wc_Order $order, Mollie_API_Object_Payment $payment)
    {
        WC_Mollie::debug(__METHOD__ . ' called.');

        // Reset state
        $order->update_status('pending');

        $order->add_order_note(sprintf(
            __('%s payment expired (%s).', 'woocommerce-mollie-payments'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'woocommerce-mollie-payments')) : '')
        ));
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    public function getReturnRedirectUrlForOrder (WC_Order $order)
    {
        /** @var WooCommerce $woocommerce */
        global $woocommerce;

        $data_helper = WC_Mollie::getDataHelper();

        if ($data_helper->hasCancelledMolliePayment($order->id))
        {
            WC_Mollie::addNotice(__('You have cancelled your payment. Please complete your order with a different payment method.', 'woocommerce-mollie-payments'));

            //return $order->get_checkout_payment_url();
            return $woocommerce->cart->get_checkout_url();
        }

        $payment = $data_helper->getActiveMolliePayment($order->id, $use_cache = false);

        if ($payment)
        {
            // TODO: remove!
            WC_Mollie::debug(__METHOD__ . ": Payment on return: " . print_r($payment, true));
        }

        return $this->get_return_url($order);
    }

    /**
     * Process a refund if supported
     * @param  int $order_id
     * @param  float $amount
     * @param  string $reason
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        if (!$order)
        {
            WC_Mollie::debug('process_refund - could not find order ' . $order_id);

            return false;
        }

        try
        {
            $payment = WC_Mollie::getDataHelper()->getActiveMolliePayment($order_id);

            if (!$payment)
            {
                WC_Mollie::debug('process_refund - could not find active Mollie payment for order ' . $order_id);

                return false;
            }
            elseif (!$payment->isPaid())
            {
                WC_Mollie::debug('process_refund - could not refund payment ' . $payment->id . ' (not paid). Order ' . $order_id);

                return false;
            }

            WC_Mollie::debug('process_refund - create refund - payment: ' . $payment->id . ', order: ' . $order_id . ', amount: ' . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));

            do_action(WC_Mollie::PLUGIN_ID . '_create_refund', $payment, $order);

            // Is test mode enabled?
            $test_mode = WC_Mollie::getSettingsHelper()->isTestModeEnabled();

            // Send refund to Mollie
            $refund = WC_Mollie::getApiHelper()->getApiClient($test_mode)->payments->refund($payment, $amount);

            WC_Mollie::debug('process_refund - refund created - refund: ' . $refund->id . ', payment: ' . $payment->id . ', order: ' . $order_id . ', amount: ' . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));

            do_action(WC_Mollie::PLUGIN_ID . '_refund_created', $refund, $order);

            $order->add_order_note(sprintf(__('Refunded %s%s (reason: %s) - Payment ID: %s, Refund %s', 'woocommerce-mollie-payments'), get_woocommerce_currency_symbol(), $amount, $reason, $refund->payment->id, $refund->id));

            return true;
        }
        catch (Exception $e)
        {
            return new WP_Error(1, $e->getMessage());
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page ($order_id)
    {
        $order = wc_get_order($order_id);

        // Order not found
        if (!$order)
        {
            return;
        }

        // Same as email instructions, just run that
        $this->displayInstructions($order, $admin_instructions = false, $plain_text = false);
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool     $admin_instructions (default: false)
     * @param bool     $plain_text (default: false)
     * @return void
     */
    public function displayInstructions(WC_Order $order, $admin_instructions = false, $plain_text = false)
    {
        // Invalid gateway
        if ($this->id !== $order->payment_method)
        {
            return;
        }

        $payment = WC_Mollie::getDataHelper()->getActiveMolliePayment($order->id);

        // Mollie payment not found or invalid gateway
        if (!$payment || $payment->method != $this->getMollieMethodId())
        {
            return;
        }

        $instructions = $this->getInstructions($order, $payment, $admin_instructions, $plain_text);

        // TODO: remove!
        WC_Mollie::debug($order->id . ' - instructions: ' . $instructions);

        if (!empty($instructions))
        {
            $instructions = wptexturize($instructions);

            if ($plain_text)
            {
                echo $instructions . PHP_EOL;
            }
            else
            {
                echo '<h2>' . __('Payment', 'woocommerce-mollie-payments') . '</h2>';
                echo wpautop($instructions) . PHP_EOL;
            }
        }
    }

    /**
     * @param WC_Order                  $order
     * @param Mollie_API_Object_Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions (WC_Order $order, Mollie_API_Object_Payment $payment, $admin_instructions, $plain_text)
    {
        // No definite payment status
        if ($payment->isOpen())
        {
            if ($admin_instructions)
            {
                // Message to admin
                return __('We have not received a definite payment status.', 'woocommerce-mollie-payments');
            }
            else
            {
                // Message to customer
                return __('We have not received a definite payment status. You will receive an email as soon as we receive a confirmation of the bank/merchant.', 'woocommerce-mollie-payments');
            }
        }

        return null;
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    protected function orderNeedsPayment (WC_Order $order)
    {
        return $order->needs_payment();
    }

    /**
     * @return Mollie_API_Object_Method|null
     */
    protected function getMollieMethod ()
    {
        try
        {
            $test_mode = WC_Mollie::getSettingsHelper()->isTestModeEnabled();

            return WC_Mollie::getDataHelper()->getPaymentMethod(
                $test_mode,
                $this->getMollieMethodId()
            );
        }
        catch (WC_Mollie_Exception_InvalidApiKey $e)
        {
        }

        return null;
    }

    /**
     * @return string
     */
    protected function getInitialStatus ()
    {
        return 'pending';
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    protected function getReturnUrl (WC_Order $order)
    {
        $return_url = WC()->api_request_url('mollie_return');
        $return_url = add_query_arg(array(
            'order_id'       => $order->id,
            'key'            => $order->order_key,
            'utm_nooverride' => 1,
        ), $return_url);

        return $return_url;
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    protected function getWebhookUrl (WC_Order $order)
    {
        $webhook_url = WC()->api_request_url(strtolower(get_class($this)));
        $webhook_url = add_query_arg(array(
            'order_id' => $order->id,
            'key'      => $order->order_key,
        ), $webhook_url);

        return apply_filters(WC_Mollie::PLUGIN_ID . '_webhook_url', $webhook_url, $order);
    }

    /**
     * @return string|NULL
     */
    protected function getSelectedIssuer ()
    {
        $issuer_id = WC_Mollie::PLUGIN_ID . '_issuer_' . $this->id;

        return !empty($_POST[$issuer_id]) ? $_POST[$issuer_id] : NULL;
    }

    /**
     * @return array
     */
    protected function getSupportedCurrencies ()
    {
        $default = array('EUR');

        return apply_filters('woocommerce_' . $this->id . '_supported_currencies', $default);
    }

    /**
     * @return bool
     */
    protected function isCurrencySupported ()
    {
        return in_array(get_woocommerce_currency(), $this->getSupportedCurrencies());
    }

    /**
     * @return bool
     */
    protected function isValidApiKeyProvided ()
    {
        $settings  = WC_Mollie::getSettingsHelper();
        $test_mode = $settings->isTestModeEnabled();
        $api_key   = $settings->getApiKey($test_mode);

        return !empty($api_key) && preg_match('/^(live|test)_\w+$/', $api_key);
    }

    /**
     * @return mixed
     */
    abstract public function getMollieMethodId ();

    /**
     * @return string
     */
    abstract protected function getDefaultTitle ();

    /**
     * @return string
     */
    abstract protected function getDefaultDescription ();
}
