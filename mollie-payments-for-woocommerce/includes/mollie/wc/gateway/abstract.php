<?php
abstract class Mollie_WC_Gateway_Abstract extends WC_Payment_Gateway
{
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD    = 'on-hold';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_REFUNDED   = 'refunded';
    const STATUS_FAILED     = 'failed';

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
     * Minimum transaction amount, zero does not define a minimum
     *
     * @var int
     */
    public $min_amount = 0;

    /**
     * Maximum transaction amount, zero does not define a maximum
     *
     * @var int
     */
    public $max_amount = 0;

    /**
     *
     */
    public function __construct ()
    {
        // No plugin id, gateway id is unique enough
        $this->plugin_id    = '';
        // Use gateway class name as gateway id
        $this->id           = strtolower(get_class($this));
        // Set gateway title (visible in admin)
        $this->method_title = 'Mollie - ' . $this->getDefaultTitle();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->display_logo = $this->get_option('display_logo') == 'yes';

        $this->_initDescription();
        $this->_initIcon();
        $this->_initMinMaxAmount();

        add_action('woocommerce_api_' . $this->id, array($this, 'webhookAction'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_after_order_table', array($this, 'displayInstructions'), 10, 3);

        if (!$this->isValidForUse())
        {
            // Disable gateway if it's not valid for use
            $this->enabled = false;
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'mollie-payments-for-woocommerce'),
                'type'        => 'checkbox',
                'label'       => sprintf(__('Enable %s', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default'     => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'mollie-payments-for-woocommerce'),
                'type'        => 'text',
                'description' => sprintf(__('This controls the title which the user sees during checkout. Default <code>%s</code>', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default'     => $this->getDefaultTitle(),
                'desc_tip'    => true,
            ),
            'display_logo' => array(
                'title'       => __('Display logo', 'mollie-payments-for-woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Display logo on checkout page. Default <code>enabled</code>', 'mollie-payments-for-woocommerce'),
                'default'     => 'yes'
            ),
            'description' => array(
                'title'       => __('Description', 'mollie-payments-for-woocommerce'),
                'type'        => 'textarea',
                'description' => sprintf(__('Payment method description that the customer will see on your checkout. Default <code>%s</code>', 'mollie-payments-for-woocommerce'), $this->getDefaultDescription()),
                'default'     => $this->getDefaultDescription(),
                'desc_tip'    => true,
            ),
        );

        if ($this->paymentConfirmationAfterCoupleOfDays())
        {
            $this->form_fields['initial_order_status'] = array(
                'title'       => __('Initial order status', 'mollie-payments-for-woocommerce'),
                'type'        => 'select',
                'options'     => array(
                    self::STATUS_ON_HOLD => wc_get_order_status_name(self::STATUS_ON_HOLD) . ' (' . __('default', 'mollie-payments-for-woocommerce') . ')',
                    self::STATUS_PENDING => wc_get_order_status_name(self::STATUS_PENDING),
                ),
                'default'     => self::STATUS_ON_HOLD,
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => sprintf(
                    __('Some payment methods take longer than a few hours to complete. The initial order state is then set to \'%s\'. This ensures the order is not cancelled when the setting %s is used.', 'mollie-payments-for-woocommerce'),
                    wc_get_order_status_name(self::STATUS_ON_HOLD),
                    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory') . '" target="_blank">' . __('Hold Stock (minutes)', 'woocommerce') . '</a>'
                ),
            );
        }
    }

    /**
     * @return string
     */
    public function getIconUrl ()
    {
        return Mollie_WC_Plugin::getPluginUrl('/assets/images/' . $this->getMollieMethodId() . '.png');
    }

    protected function _initIcon ()
    {
        if ($this->display_logo)
        {
            $default_icon = $this->getIconUrl();
            $this->icon   = apply_filters($this->id . '_icon_url', $default_icon);
        }
    }

    protected function _initDescription ()
    {
        $description = '';

        if (Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled())
        {
            $description .= '<strong>' . __('Test mode enabled.', 'mollie-payments-for-woocommerce') . '</strong><br/>';
        }

        $description .= $this->get_option('description');

        $this->description = $description;
    }

    protected function _initMinMaxAmount ()
    {
        if ($mollie_method = $this->getMollieMethod())
        {
            $this->min_amount = $mollie_method->getMinimumAmount() ? $mollie_method->getMinimumAmount() : 0;
            $this->max_amount = $mollie_method->getMaximumAmount() ? $mollie_method->getMaximumAmount() : 0;
        }
    }

    public function admin_options ()
    {
        if (!$this->enabled && count($this->errors))
        {
            echo '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'mollie-payments-for-woocommerce') . '</strong>: '
                . implode('<br/>', $this->errors)
                . '</p></div>';

            return;
        }

        parent::admin_options();
    }

    /**
     * Check if this gateway can be used
     *
     * @return bool
     */
    protected function isValidForUse()
    {
        $settings = Mollie_WC_Plugin::getSettingsHelper();

        if (!$this->isValidApiKeyProvided())
        {
            $test_mode = $settings->isTestModeEnabled();

            $this->errors[] = ($test_mode ? __('Test mode enabled.', 'mollie-payments-for-woocommerce') . ' ' : '') . sprintf(
                /* translators: The surrounding %s's Will be replaced by a link to the global setting page */
                    __('No API key provided. Please %sset you Mollie API key%s first.', 'mollie-payments-for-woocommerce'),
                    '<a href="' . $settings->getGlobalSettingsUrl() . '">',
                    '</a>'
                );

            return false;
        }

        if (null === $this->getMollieMethod())
        {
            $this->errors[] = sprintf(
            /* translators: Placeholder 1: payment method title. The surrounding %s's Will be replaced by a link to the Mollie profile */
                __('%s not enabled in your Mollie profile. You can enabled it by editing your %sMollie profile%s.', 'mollie-payments-for-woocommerce'),
                $this->getDefaultTitle(),
                '<a href="https://www.mollie.com/beheer/account/profielen/" target="_blank">',
                '</a>'
            );

            return false;
        }

        if (!$this->isCurrencySupported())
        {
            $this->errors[] = sprintf(
            /* translators: Placeholder 1: WooCommerce currency, placeholder 2: Supported Mollie currencies */
                __('Shop currency %s not supported by Mollie. Mollie only supports: %s.', 'mollie-payments-for-woocommerce'),
                get_woocommerce_currency(),
                implode(', ', $this->getSupportedCurrencies())
            );

            return false;
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
        if (!parent::is_available())
        {
            return false;
        }

        if (WC()->cart && $this->get_order_total() > 0)
        {
            // Validate min amount
            if (0 < $this->min_amount && $this->min_amount > $this->get_order_total())
            {
                return false;
            }

            // Validate max amount
            if (0 < $this->max_amount && $this->max_amount < $this->get_order_total())
            {
                return false;
            }
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
    protected function paymentConfirmationAfterCoupleOfDays ()
    {
        return false;
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function process_payment ($order_id)
    {
        $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder($order_id);

        if (!$order)
        {
            Mollie_WC_Plugin::debug($this->id . ': Could not process payment, order ' . $order_id . ' not found.');

            Mollie_WC_Plugin::addNotice(sprintf(__('Could not load order %s', 'mollie-payments-for-woocommerce'), $order_id), 'error');

            return array('result' => 'failure');
        }

        $initial_order_status = $this->getInitialOrderStatus();

        // Overwrite plugin-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status', $initial_order_status);

        // Overwrite gateway-wide
        $initial_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_order_status);

        $settings_helper     = Mollie_WC_Plugin::getSettingsHelper();

        // Is test mode enabled?
        $test_mode = $settings_helper->isTestModeEnabled();

        $payment_description = $settings_helper->getPaymentDescription();
        $payment_locale      = $settings_helper->getPaymentLocale();
        $mollie_method       = $this->getMollieMethodId();
        $selected_issuer     = $this->getSelectedIssuer();
        $return_url          = $this->getReturnUrl($order);
        $webhook_url         = $this->getWebhookUrl($order);
        $customer_id         = Mollie_WC_Plugin::getDataHelper()->getUserMollieCustomerId($order->customer_user, $test_mode);

        $payment_description = strtr($payment_description, array(
            '{order_number}' => $order->get_order_number(),
            '{order_date}'   => date_i18n(wc_date_format(), strtotime($order->order_date)),
        ));

        $data = array_filter(array(
            'amount'          => $order->get_total(),
            'description'     => $payment_description,
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
            'customerId'      => $customer_id,
        ));

        $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $order);

        try
        {
            Mollie_WC_Plugin::debug($this->id . ': Create payment for order ' . $order->id, true);

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_create_payment', $data, $order);

            // Create Mollie payment with customer id.
            try
            {
                $payment = Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->payments->create($data);
            }
            catch (Mollie_API_Exception $e)
            {
                if ($e->getField() !== 'customerId')
                {
                    throw $e;
                }

                // Retry without customer id.
                unset($data['customerId']);
                $payment = Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->payments->create($data);
            }

            // Set active Mollie payment
            Mollie_WC_Plugin::getDataHelper()->setActiveMolliePayment($order->id, $payment);

            // Set Mollie customer
            Mollie_WC_Plugin::getDataHelper()->setUserMollieCustomerId($order->customer_user, $payment->customerId);

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_payment_created', $payment, $order);

            Mollie_WC_Plugin::debug($this->id . ': Payment ' . $payment->id . ' (' . $payment->mode . ') created for order ' . $order->id);

            // Set initial status
            // Status is only updated if the new status is not the same as the default order status (pending)
            $this->updateOrderStatus(
                $order,
                $initial_order_status,
                __('Awaiting payment confirmation.', 'mollie-payments-for-woocommerce') . "\n"
            );

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
                __('%s payment started (%s).', 'mollie-payments-for-woocommerce'),
                $this->method_title,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
            ));

            // Empty cart
            WC()->cart->empty_cart();

            Mollie_WC_Plugin::debug("Cart emptied, redirect user to payment URL: {$payment->getPaymentUrl()}");

            return array(
                'result'   => 'success',
                'redirect' => $this->getProcessPaymentRedirect($order, $payment),
            );
        }
        catch (Mollie_API_Exception $e)
        {
            Mollie_WC_Plugin::debug($this->id . ': Failed to create payment for order ' . $order->id . ': ' . $e->getMessage());

            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(__('Could not create %s payment.', 'mollie-payments-for-woocommerce'), $this->title);

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                $message .= ' ' . $e->getMessage();
            }

            Mollie_WC_Plugin::addNotice($message, 'error');
        }

        return array('result' => 'failure');
    }

    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order $order
     * @param Mollie_API_Object_Payment $payment
     *
     * @return string
     */
    protected function getProcessPaymentRedirect(WC_Order $order, Mollie_API_Object_Payment $payment)
    {
        /*
         * Redirect to payment URL
         */
        return $payment->getPaymentUrl();
    }

    /**
     * @param WC_Order $order
     * @param string $new_status
     * @param string $note
     */
    public function updateOrderStatus (WC_Order $order, $new_status, $note = '')
    {
        $order->update_status($new_status, $note);

        switch ($new_status)
        {
            case self::STATUS_ON_HOLD:
                if (!get_post_meta($order->id, '_order_stock_reduced', $single = true))
                {
                    // Reduce order stock
                    $order->reduce_order_stock();

                    Mollie_WC_Plugin::debug(__METHOD__ . ":  Stock for order {$order->id} reduced.");
                }

                break;

            case self::STATUS_PENDING:
            case self::STATUS_FAILED:
            case self::STATUS_CANCELLED:
                if (get_post_meta($order->id, '_order_stock_reduced', $single = true))
                {
                    // Restore order stock
                    Mollie_WC_Plugin::getDataHelper()->restoreOrderStock($order);

                    Mollie_WC_Plugin::debug(__METHOD__ . " Stock for order {$order->id} restored.");
                }

                break;
        }
    }

    public function webhookAction ()
    {
        // Webhook test by Mollie
        if (isset($_GET['testByMollie']))
        {
            Mollie_WC_Plugin::debug(__METHOD__ . ': Webhook tested by Mollie.', true);
            return;
        }

        if (empty($_GET['order_id']) || empty($_GET['key']))
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ":  No order ID or order key provided.");
            return;
        }

        $order_id    = $_GET['order_id'];
        $key         = $_GET['key'];

        $data_helper = Mollie_WC_Plugin::getDataHelper();
        $order       = $data_helper->getWcOrder($order_id);

        if (!$order)
        {
            Mollie_WC_Plugin::setHttpResponseCode(404);
            Mollie_WC_Plugin::debug(__METHOD__ . ":  Could not find order $order_id.");
            return;
        }

        if (!$order->key_is_valid($key))
        {
            Mollie_WC_Plugin::setHttpResponseCode(401);
            Mollie_WC_Plugin::debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
            return;
        }

        // No Mollie payment id provided
        if (empty($_REQUEST['id']))
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ': No payment ID provided.', true);
            return;
        }

        $payment_id = $_REQUEST['id'];
        $test_mode  = $data_helper->getActiveMolliePaymentMode($order_id) == 'test';

        // Load the payment from Mollie, do not use cache
        $payment = $data_helper->getPayment($payment_id, $test_mode, $use_cache = false);

        // Payment not found
        if (!$payment)
        {
            Mollie_WC_Plugin::setHttpResponseCode(404);
            Mollie_WC_Plugin::debug(__METHOD__ . ": payment $payment_id not found.", true);
            return;
        }

        if ($order_id != $payment->metadata->order_id)
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug(__METHOD__ . ": Order ID does not match order_id in payment metadata. Payment ID {$payment->id}, order ID $order_id");
            return;
        }

        // Payment requires different gateway, payment method changed on Mollie platform?
        if ($payment->method != $this->getMollieMethodId())
        {
            Mollie_WC_Plugin::setHttpResponseCode(400);
            Mollie_WC_Plugin::debug($this->id . ": Invalid gateway. This gateways can process Mollie " . $this->getMollieMethodId() . " payments. This payment has payment method " . $payment->method, true);
            return;
        }

        // Order does not need a payment
        if (!$this->orderNeedsPayment($order))
        {
            // Duplicate webhook call
            Mollie_WC_Plugin::setHttpResponseCode(204);
            Mollie_WC_Plugin::debug($this->id . ": Order $order_id does not need a payment (payment webhook {$payment->id}).", true);
            return;
        }

        Mollie_WC_Plugin::debug($this->id . ": Mollie payment {$payment->id} (" . $payment->mode . ") webhook call for order {$order->id}.", true);

        $method_name = 'onWebhook' . ucfirst($payment->status);

        if (method_exists($this, $method_name))
        {
            $this->{$method_name}($order, $payment);
        }
        else
        {
            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment status, placeholder 3: payment ID */
                __('%s payment %s (%s).', 'mollie-payments-for-woocommerce'),
                $this->method_title,
                $payment->status,
                $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
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
        Mollie_WC_Plugin::debug(__METHOD__ . ' called.');

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
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));
    }

    /**
     * @param Wc_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookCancelled (Wc_Order $order, Mollie_API_Object_Payment $payment)
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ' called.');

        // Unset active Mollie payment id
        Mollie_WC_Plugin::getDataHelper()
            ->unsetActiveMolliePayment($order->id)
            ->setCancelledMolliePaymentId($order->id, $payment->id);

        // New order status
        $new_order_status = self::STATUS_PENDING;

        // Overwrite plugin-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled', $new_order_status);

        // Overwrite gateway-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled_' . $this->id, $new_order_status);

        // Reset state
        $this->updateOrderStatus($order, $new_order_status);

        // User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%s payment cancelled (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));
    }

    /**
     * @param Wc_Order $order
     * @param Mollie_API_Object_Payment $payment
     */
    protected function onWebhookExpired (Wc_Order $order, Mollie_API_Object_Payment $payment)
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ' called.');

        // New order status
        $new_order_status = self::STATUS_CANCELLED;

        // Overwrite plugin-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired', $new_order_status);

        // Overwrite gateway-wide
        $new_order_status = apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired_' . $this->id, $new_order_status);

        // Cancel order
        $this->updateOrderStatus($order, $new_order_status);

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%s payment expired (%s).', 'mollie-payments-for-woocommerce'),
            $this->method_title,
            $payment->id . ($payment->mode == 'test' ? (' - ' . __('test mode', 'mollie-payments-for-woocommerce')) : '')
        ));
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    public function getReturnRedirectUrlForOrder (WC_Order $order)
    {
        $data_helper = Mollie_WC_Plugin::getDataHelper();

        if ($data_helper->hasCancelledMolliePayment($order->id))
        {
            Mollie_WC_Plugin::addNotice(__('You have cancelled your payment. Please complete your order with a different payment method.', 'mollie-payments-for-woocommerce'));

            if (method_exists($order, 'get_checkout_payment_url'))
            {
                /*
                 * Return to order payment page
                 */
                return $order->get_checkout_payment_url(false);
            }

            /*
             * Return to cart
             */
            return WC()->cart->get_checkout_url();
        }

        /*
         * Return to order received page
         */
        return $this->get_return_url($order);
    }

    /**
     * Process a refund if supported
     * @param int    $order_id
     * @param float  $amount
     * @param string $reason
     * @return bool|wp_error True or false based on success, or a WP_Error object
     * @since WooCommerce 2.2
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder($order_id);

        if (!$order)
        {
            Mollie_WC_Plugin::debug('process_refund - could not find order ' . $order_id);

            return false;
        }

        try
        {
            $payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment($order_id);

            if (!$payment)
            {
                Mollie_WC_Plugin::debug('process_refund - could not find active Mollie payment for order ' . $order_id);

                return false;
            }
            elseif (!$payment->isPaid())
            {
                Mollie_WC_Plugin::debug('process_refund - could not refund payment ' . $payment->id . ' (not paid). Order ' . $order_id);

                return false;
            }

            Mollie_WC_Plugin::debug('process_refund - create refund - payment: ' . $payment->id . ', order: ' . $order_id . ', amount: ' . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_create_refund', $payment, $order);

            // Is test mode enabled?
            $test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

            // Send refund to Mollie
            $refund = Mollie_WC_Plugin::getApiHelper()->getApiClient($test_mode)->payments->refund($payment, array(
                'amount'      => $amount,
                'description' => $reason
            ));

            Mollie_WC_Plugin::debug('process_refund - refund created - refund: ' . $refund->id . ', payment: ' . $payment->id . ', order: ' . $order_id . ', amount: ' . $amount . (!empty($reason) ? ', reason: ' . $reason : ''));

            do_action(Mollie_WC_Plugin::PLUGIN_ID . '_refund_created', $refund, $order);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
                __('Refunded %s%s (reason: %s) - Payment ID: %s, Refund: %s', 'mollie-payments-for-woocommerce'),
                get_woocommerce_currency_symbol(),
                $amount,
                $reason,
                $refund->payment->id,
                $refund->id
            ));

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
        $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder($order_id);

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

        $payment = Mollie_WC_Plugin::getDataHelper()->getActiveMolliePayment($order->id);

        // Mollie payment not found or invalid gateway
        if (!$payment || $payment->method != $this->getMollieMethodId())
        {
            return;
        }

        $instructions = $this->getInstructions($order, $payment, $admin_instructions, $plain_text);

        if (!empty($instructions))
        {
            $instructions = wptexturize($instructions);

            if ($plain_text)
            {
                echo $instructions . PHP_EOL;
            }
            else
            {
                echo '<h2>' . __('Payment', 'mollie-payments-for-woocommerce') . '</h2>';
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
                return __('We have not received a definite payment status.', 'mollie-payments-for-woocommerce');
            }
            else
            {
                // Message to customer
                return __('We have not received a definite payment status. You will receive an email as soon as we receive a confirmation of the bank/merchant.', 'mollie-payments-for-woocommerce');
            }
        }
        elseif ($payment->isPaid())
        {
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
     * @return bool
     */
    protected function orderNeedsPayment (WC_Order $order)
    {
        if ($order->needs_payment())
        {
            return true;
        }

        // Has initial order status 'on-hold'
        if ($this->getInitialOrderStatus() === self::STATUS_ON_HOLD && Mollie_WC_Plugin::getDataHelper()->hasOrderStatus($order, self::STATUS_ON_HOLD))
        {
            return true;
        }

        return false;
    }

    /**
     * @return Mollie_API_Object_Method|null
     */
    public function getMollieMethod ()
    {
        try
        {
            $test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

            return Mollie_WC_Plugin::getDataHelper()->getPaymentMethod(
                $test_mode,
                $this->getMollieMethodId()
            );
        }
        catch (Mollie_WC_Exception_InvalidApiKey $e)
        {
        }

        return null;
    }

    /**
     * @return string
     */
    protected function getInitialOrderStatus ()
    {
        if ($this->paymentConfirmationAfterCoupleOfDays())
        {
            return $this->get_option('initial_order_status');
        }

        return self::STATUS_PENDING;
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    protected function getReturnUrl (WC_Order $order)
    {
        $site_url   = get_site_url();

        $return_url = WC()->api_request_url('mollie_return');
        $return_url = add_query_arg(array(
            'order_id'       => $order->id,
            'key'            => $order->order_key,
        ), $return_url);

        $lang_url   = $this->getSiteUrlWithLanguage();
        $return_url = str_replace($site_url, $lang_url, $return_url);

        return apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_return_url', $return_url, $order);
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    protected function getWebhookUrl (WC_Order $order)
    {
        $site_url    = get_site_url();

        $webhook_url = WC()->api_request_url(strtolower(get_class($this)));
        $webhook_url = add_query_arg(array(
            'order_id' => $order->id,
            'key'      => $order->order_key,
        ), $webhook_url);

        $lang_url    = $this->getSiteUrlWithLanguage();
        $webhook_url = str_replace($site_url, $lang_url, $webhook_url);

        return apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_webhook_url', $webhook_url, $order);
    }

    /**
     * Check if any multi language plugins are enabled and return the correct site url.
     *
     * @return string
     */
    protected function getSiteUrlWithLanguage()
    {
        /**
         * function is_plugin_active() is not available. Lets include it to use it.
         */
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        $site_url = get_site_url();
        $slug     = ''; // default is NO slug/language

        if (is_plugin_active('polylang/polylang.php')
            || is_plugin_active('mlang/mlang.php')
            || is_plugin_active('mlanguage/mlanguage.php')
        )
        {
            // we probably have a multilang site. Retrieve current language.
            $slug = get_bloginfo('language');
            $pos  = strpos($slug, '-');
            if ($pos !== false)
                $slug = substr($slug, 0, $pos);
                
            $slug = '/' . $slug;
        }

        return str_replace($site_url, $site_url . $slug, $site_url);
    }

    /**
     * @return string|NULL
     */
    protected function getSelectedIssuer ()
    {
        $issuer_id = Mollie_WC_Plugin::PLUGIN_ID . '_issuer_' . $this->id;

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
        $settings  = Mollie_WC_Plugin::getSettingsHelper();
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
    abstract public function getDefaultTitle ();

    /**
     * @return string
     */
    abstract protected function getDefaultDescription ();
}
