<?php
class WC_Mollie_Helper_Data
{
    /**
     * @var Mollie_API_Object_Method[]|Mollie_API_Object_List|array
     */
    protected static $api_methods;

    /**
     * @var Mollie_API_Object_Issuer[]|Mollie_API_Object_List|array
     */
    protected static $api_issuers;

    /**
     * @var WC_Mollie_Helper_Api
     */
    protected $api_helper;

    /**
     * @param WC_Mollie_Helper_Api $api_helper
     */
    public function __construct (WC_Mollie_Helper_Api $api_helper)
    {
        $this->api_helper = $api_helper;
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
            $transient_id = WC_Mollie::PLUGIN_ID . '_payment_' . $payment_id;

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
            WC_Mollie::debug(__FUNCTION__ . ": Could not load payment $payment_id (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return NULL;
    }

    /**
     * @param bool $test_mode (default: false)
     * @return array|Mollie_API_Object_List|Mollie_API_Object_Method[]
     */
    public function getPaymentMethods ($test_mode = false)
    {
        try
        {
            $transient_id = WC_Mollie::PLUGIN_ID . '_api_methods_' . ($test_mode ? 'test' : 'live');

            if (empty(self::$api_methods))
            {
                $cached = @unserialize(get_transient($transient_id));

                if ($cached && $cached instanceof Mollie_API_Object_List)
                {
                    self::$api_methods = $cached;
                }
                else
                {
                    self::$api_methods = $this->api_helper->getApiClient($test_mode)->methods->all();

                    set_transient($transient_id, self::$api_methods, MINUTE_IN_SECONDS * 5);
                }
            }

            return self::$api_methods;
        }
        catch (Mollie_API_Exception $e)
        {
            WC_Mollie::debug(__FUNCTION__ . ": Could not load Mollie methods (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
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
        try
        {
            $transient_id = WC_Mollie::PLUGIN_ID . '_api_issuers_' . ($test_mode ? 'test' : 'live');

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
            WC_Mollie::debug(__FUNCTION__ . ": Could not load Mollie issuers (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
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

        return $this;
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
}
