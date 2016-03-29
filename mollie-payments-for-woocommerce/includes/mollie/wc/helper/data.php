<?php
class Mollie_WC_Helper_Data
{
    /**
     * Transient prefix. We can not use plugin slug because this
     * will generate to long keys for the wp_options table.
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'mollie-wc-';

    /**
     * @var Mollie_API_Object_Method[]|Mollie_API_Object_List|array
     */
    protected static $api_methods;

    /**
     * @var Mollie_API_Object_Issuer[]|Mollie_API_Object_List|array
     */
    protected static $api_issuers;

    /**
     * @var Mollie_WC_Helper_Api
     */
    protected $api_helper;

    /**
     * @param Mollie_WC_Helper_Api $api_helper
     */
    public function __construct (Mollie_WC_Helper_Api $api_helper)
    {
        $this->api_helper = $api_helper;
    }

    /**
     * Get current locale
     *
     * @return string
     */
    protected function getCurrentLocale ()
    {
        return apply_filters('wpml_current_language', get_locale());
    }

    /**
     * @param string $transient
     * @return string
     */
    protected function getTransientId ($transient)
    {
        /*
         * WordPress will save two options to wp_options table:
         * 1. _transient_<transient_id>
         * 2. _transient_timeout_<transient_id>
         */
        $transient_id       = self::TRANSIENT_PREFIX . $transient;
        $option_name        = '_transient_timeout_' . $transient_id;
        $option_name_length = strlen($option_name);

        if ($option_name_length > 64)
        {
            trigger_error("Transient id $transient_id is to long. Option name $option_name ($option_name_length) will be to long for database column wp_options.option_name which is varchar(64).", E_USER_WARNING);
        }

        return $transient_id;
    }

    /**
     * Get WooCommerce order
     *
     * @param int $order_id Order ID
     * @return WC_Order|bool
     */
    public function getWcOrder ($order_id)
    {
        if (function_exists('wc_get_order'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return wc_get_order($order_id);
        }

        $order = new WC_Order();

        if ($order->get_order($order_id))
        {
            return $order;
        }

        return false;
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    public function getOrderStatus (WC_Order $order)
    {
        if (method_exists($order, 'get_status'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return $order->get_status();
        }

        return $order->status;
    }

    /**
     * Check if a order has a status
     *
     * @param string|string[] $status
     * @return bool
     */
    public function hasOrderStatus (WC_Order $order, $status)
    {
        if (method_exists($order, 'has_status'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return $order->has_status($status);
        }

        if (!is_array($status))
        {
            $status = array($status);
        }

        return in_array($this->getOrderStatus($order), $status);
    }

    /**
     * Get payment gateway class by order data.
     *
     * @param int|WC_Order $order
     * @return WC_Payment_Gateway|bool
     */
    public function getWcPaymentGatewayByOrder ($order)
    {
        if (function_exists('wc_get_payment_gateway_by_order'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return wc_get_payment_gateway_by_order($order);
        }

        if (WC()->payment_gateways())
        {
            $payment_gateways = WC()
                ->payment_gateways
                ->payment_gateways();
        }
        else
        {
            $payment_gateways = array();
        }

        if (!($order instanceof WC_Order))
        {
            $order = $this->getWcOrder($order);

            if (!$order)
            {
                return false;
            }
        }

        return isset($payment_gateways[$order->payment_method]) ? $payment_gateways[$order->payment_method] : false;
    }

    /**
     * Called when page 'WooCommerce -> Checkout -> Checkout Options' is saved
     *
     * @see \Mollie_WC_Plugin::init
     */
    public function deleteTransients ()
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ': Mollie settings saved, delete transients');

        $transient_names = array(
            'api_methods_test',
            'api_methods_live',
            'api_issuers_test',
            'api_issuers_live',
        );

        $languages   = array_keys(apply_filters('wpml_active_languages', array()));
        $languages[] = $this->getCurrentLocale();

        foreach ($transient_names as $transient_name)
        {
            foreach ($languages as $language)
            {
                delete_transient($this->getTransientId($transient_name . "_$language"));
            }
        }
    }

    /**
     * Get Mollie payment from cache or load from Mollie
     * Skip cache by setting $use_cache to false
     *
     * @param string $payment_id
     * @param bool   $test_mode (default: false)
     * @param bool   $use_cache (default: true)
     * @return Mollie_API_Object_Payment|null
     */
    public function getPayment ($payment_id, $test_mode = false, $use_cache = true)
    {
        try
        {
            $transient_id = $this->getTransientId('payment_' . $payment_id);

            if ($use_cache)
            {
                $payment = @unserialize(get_transient($transient_id));

                if ($payment && $payment instanceof Mollie_API_Object_Payment)
                {
                    return $payment;
                }
            }

            $payment = $this->api_helper->getApiClient($test_mode)->payments->get($payment_id);

            set_transient($transient_id, $payment, MINUTE_IN_SECONDS * 5);

            return $payment;
        }
        catch (Exception $e)
        {
            Mollie_WC_Plugin::debug(__FUNCTION__ . ": Could not load payment $payment_id (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return NULL;
    }

    /**
     * @param bool $test_mode (default: false)
     * @param bool $use_cache (default: true)
     * @return array|Mollie_API_Object_List|Mollie_API_Object_Method[]
     */
    public function getPaymentMethods ($test_mode = false, $use_cache = true)
    {
        // Already initialized
        if ($use_cache && !empty(self::$api_methods))
        {
            return self::$api_methods;
        }

        $locale = $this->getCurrentLocale();

        try
        {
            $transient_id = $this->getTransientId('api_methods_' . ($test_mode ? 'test' : 'live') . "_$locale");

            if ($use_cache)
            {
                $cached = @unserialize(get_transient($transient_id));

                if ($cached && $cached instanceof Mollie_API_Object_List)
                {
                    return (self::$api_methods = $cached);
                }
            }

            self::$api_methods = $this->api_helper->getApiClient($test_mode)->methods->all();

            set_transient($transient_id, self::$api_methods, MINUTE_IN_SECONDS * 5);

            return self::$api_methods;
        }
        catch (Mollie_API_Exception $e)
        {
            Mollie_WC_Plugin::debug(__FUNCTION__ . ": Could not load Mollie methods (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return array();
    }

    /**
     * @param bool   $test_mode (default: false)
     * @param string $method
     * @return Mollie_API_Object_Method|null
     */
    public function getPaymentMethod ($test_mode = false, $method)
    {
        $payment_methods = $this->getPaymentMethods($test_mode);

        foreach ($payment_methods as $payment_method)
        {
            if ($payment_method->id == $method)
            {
                return $payment_method;
            }
        }

        return null;
    }

    /**
     * @param bool        $test_mode (default: false)
     * @param string|null $method
     * @return array|Mollie_API_Object_Issuer[]|Mollie_API_Object_List
     */
    public function getIssuers ($test_mode = false, $method = NULL)
    {
        $locale = $this->getCurrentLocale();

        try
        {
            $transient_id = $this->getTransientId('api_issuers_' . ($test_mode ? 'test' : 'live')  . "_$locale");

            if (empty(self::$api_issuers))
            {
                $cached = @unserialize(get_transient($transient_id));

                if ($cached && $cached instanceof Mollie_API_Object_List)
                {
                    self::$api_issuers = $cached;
                }
                else
                {
                    self::$api_issuers = $this->api_helper->getApiClient($test_mode)->issuers->all();

                    set_transient($transient_id, self::$api_issuers, MINUTE_IN_SECONDS * 5);
                }
            }

            // Filter issuers by method
            if ($method !== NULL)
            {
                $method_issuers = array();

                foreach(self::$api_issuers AS $issuer)
                {
                    if ($issuer->method === $method)
                    {
                        $method_issuers[] = $issuer;
                    }
                }

                return $method_issuers;
            }

            return self::$api_issuers;
        }
        catch (Mollie_API_Exception $e)
        {
            Mollie_WC_Plugin::debug(__FUNCTION__ . ": Could not load Mollie issuers (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return array();
    }

    /**
     * Save active Mollie payment id for order
     *
     * @param int                       $order_id
     * @param Mollie_API_Object_Payment $payment
     * @return $this
     */
    public function setActiveMolliePayment ($order_id, Mollie_API_Object_Payment $payment)
    {
        add_post_meta($order_id, '_mollie_payment_id', $payment->id, $single = true);
        add_post_meta($order_id, '_mollie_payment_mode', $payment->mode, $single = true);

        delete_post_meta($order_id, '_mollie_cancelled_payment_id');

        if ($payment->customerId)
        {
            add_post_meta($order_id, '_mollie_customer_id', $payment->customerId, $single = true);
        }

        return $this;
    }

    /**
     * @param int         $user_id
     * @param string|null $customer_id
     * @return $this
     */
    public function setUserMollieCustomerId ($user_id, $customer_id)
    {
        if (!empty($customer_id))
        {
            update_user_meta($user_id, 'mollie_customer_id', $customer_id);
        }

        return $this;
    }

    /**
     * @param int  $user_id
     * @param bool $test_mode
     * @return null|string
     */
    public function getUserMollieCustomerId ($user_id, $test_mode = FALSE)
    {
        if (empty($user_id))
        {
            return NULL;
        }

        $customer_id = get_user_meta($user_id, 'mollie_customer_id', $single = true);

        if (empty($customer_id))
        {
            try
            {
                $userdata = get_userdata($user_id);

                $customer = $this->api_helper->getApiClient($test_mode)->customers->create(array(
                    'name'     => trim($userdata->user_nicename),
                    'email'    => trim($userdata->user_email),
                    'locale'   => trim($this->getCurrentLocale()),
                    'metadata' => array('user_id' => $user_id),
                ));

                $this->setUserMollieCustomerId($user_id, $customer->id);

                $customer_id = $customer->id;
            }
            catch (Exception $e)
            {
                Mollie_WC_Plugin::debug(
                    __FUNCTION__ . ": Could not create customer $user_id (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')'
                );
            }
        }

        return $customer_id;
    }

    /**
     * Delete active Mollie payment id for order
     *
     * @param int $order_id
     * @return $this
     */
    public function unsetActiveMolliePayment ($order_id)
    {
        delete_post_meta($order_id, '_mollie_payment_id');
        delete_post_meta($order_id, '_mollie_payment_mode');

        return $this;
    }

    /**
     * Get active Mollie payment id for order
     *
     * @param int $order_id
     * @return string
     */
    public function getActiveMolliePaymentId ($order_id)
    {
        return get_post_meta($order_id, '_mollie_payment_id', $single = true);
    }

    /**
     * Get active Mollie payment mode for order
     *
     * @param int $order_id
     * @return string test or live
     */
    public function getActiveMolliePaymentMode ($order_id)
    {
        return get_post_meta($order_id, '_mollie_payment_mode', $single = true);
    }

    /**
     * @param int  $order_id
     * @param bool $use_cache
     * @return Mollie_API_Object_Payment|null
     */
    public function getActiveMolliePayment ($order_id, $use_cache = true)
    {
        if ($this->hasActiveMolliePayment($order_id))
        {
            return $this->getPayment(
                $this->getActiveMolliePaymentId($order_id),
                $this->getActiveMolliePaymentMode($order_id) == 'test',
                $use_cache
            );
        }

        return null;
    }

    /**
     * Check if the order has an active Mollie payment
     *
     * @param int $order_id
     * @return bool
     */
    public function hasActiveMolliePayment ($order_id)
    {
        $mollie_payment_id = $this->getActiveMolliePaymentId($order_id);

        return !empty($mollie_payment_id);
    }

    /**
     * @param int $order_id
     * @param string $payment_id
     * @return $this
     */
    public function setCancelledMolliePaymentId ($order_id, $payment_id)
    {
        add_post_meta($order_id, '_mollie_cancelled_payment_id', $payment_id, $single = true);

        return $this;
    }

    /**
     * @param int $order_id
     * @return string|false
     */
    public function getCancelledMolliePaymentId ($order_id)
    {
        return get_post_meta($order_id, '_mollie_cancelled_payment_id', $single = true);
    }

    /**
     * Check if the order has been cancelled
     *
     * @param int $order_id
     * @return bool
     */
    public function hasCancelledMolliePayment ($order_id)
    {
        $cancelled_payment_id = $this->getCancelledMolliePaymentId($order_id);

        return !empty($cancelled_payment_id);
    }

    /**
     * @param WC_Order $order
     */
    public function restoreOrderStock (WC_Order $order)
    {
        foreach ($order->get_items() as $item)
        {
            if ($item['product_id'] > 0)
            {
                $product = $order->get_product_from_item($item);

                if ($product && $product->exists() && $product->managing_stock())
                {
                    $old_stock = $product->stock;

                    $qty = apply_filters( 'woocommerce_order_item_quantity', $item['qty'], $order, $item);

                    $new_quantity = $product->increase_stock( $qty );

                    do_action('woocommerce_auto_stock_restored', $product, $item);

                    $order->add_order_note(sprintf(
                        __('Item #%s stock incremented from %s to %s.', 'woocommerce'),
                        $item['product_id'],
                        $old_stock,
                        $new_quantity
                    ));

                    $order->send_stock_notifications($product, $new_quantity, $item['qty']);
                }
            }
        }

        // Mark order stock as not-reduced
        delete_post_meta($order->id, '_order_stock_reduced');
    }
}
