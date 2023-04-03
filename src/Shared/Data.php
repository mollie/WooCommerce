<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared;

use Exception;
use InvalidArgumentException;
use Mollie\Api\Resources\Method;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;
use WC_Customer;
use WC_Order;

class Data
{
    /**
     * Transient prefix. We can not use plugin slug because this
     * will generate to long keys for the wp_options table.
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'mollie-wc-';

    /**
     * @var \Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection|array
     */
    protected static $regular_api_methods = [];

    /**
     * @var \Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection|array
     */
    protected static $recurring_api_methods = [];

    /**
     * @var \Mollie\Api\Resources\MethodCollection[]
     */
    protected static $method_issuers = [];

    /**
     * @var Api
     */
    protected $api_helper;
    protected $settingsHelper;
    /**
     * @var Logger
     */
    protected $logger;
    protected $pluginId;
    protected $pluginPath;

    public function __construct(Api $api_helper, Logger $logger, string $pluginId, Settings $settingsHelper, string $pluginPath)
    {
        $this->api_helper = $api_helper;
        $this->settingsHelper = $settingsHelper;
        $this->logger = $logger;
        $this->pluginId = $pluginId;
        $this->pluginPath = $pluginPath;
    }

    public function getPluginId(): string
    {
        return $this->pluginId;
    }

    public function pluginPath(): string
    {
        return $this->pluginPath;
    }

    public function isBlockPluginActive(): bool
    {
        return class_exists('\Automattic\WooCommerce\Blocks\Package');
    }

    public function isSubscriptionPluginActive(): bool
    {
        $subscriptionPlugin = is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php');
        return apply_filters('mollie_wc_subscription_plugin_active', $subscriptionPlugin);
    }

    /**
     * @return bool
     */
    public function isValidApiKeyProvided()
    {
        $settings = $this->settingsHelper;
        $api_key = $settings->getApiKey();

        return !empty($api_key)
            && preg_match(
                '/^(live|test)_\w{30,}$/',
                $api_key
            );
    }

    public function getGlobalSettingsUrl()
    {
        return $this->settingsHelper->getGlobalSettingsUrl();
    }

    /**
     * @return bool
     */
    public function isTestModeEnabled(): bool
    {
        return $this->settingsHelper->isTestModeEnabled();
    }

    /**
     * @param bool $overrideTestMode
     *
     * @return null|string
     */
    public function getApiKey($overrideTestMode = 2): ?string
    {
        return $this->settingsHelper->getApiKey($overrideTestMode);
    }

    public function processSettings($gateway)
    {

        $this->settingsHelper->processSettings($gateway);
    }

    public function processAdminOptions($gateway)
    {
        $this->settingsHelper->adminOptions($gateway);
    }

    public function getPaymentLocale()
    {

        return $this->settingsHelper->getPaymentLocale();
    }

    /**
     * Get current locale
     *
     * @return string
     */
    protected function getCurrentLocale()
    {
        return apply_filters('wpml_current_language', get_locale());
    }

    /**
     * @param string $transient
     * @return string
     */
    public function getTransientId($transient)
    {
        /*
         * WordPress will save two options to wp_options table:
         * 1. _transient_<transient_id>
         * 2. _transient_timeout_<transient_id>
         */
        $transient_id = self::TRANSIENT_PREFIX . $transient;
        $option_name = '_transient_timeout_' . $transient_id;
        $option_name_length = strlen($option_name);

        $max_option_name_length = 191;

        if ($option_name_length > $max_option_name_length) {
            trigger_error(sprintf('Transient id %s is to long. Option name %s (%s) will be to long for database column wp_options.option_name which is varchar(%s).', esc_html($transient_id), esc_html($option_name), esc_html($option_name_length), esc_html($max_option_name_length)), E_USER_WARNING);
        }

        return $transient_id;
    }

    /**
     * Get Mollie payment from cache or load from Mollie
     * Skip cache by setting $use_cache to false
     *
     * @param string $payment_id
     * @param string   $apiKey (default: false)
     * @param bool   $use_cache (default: true)
     *
     * @return \Mollie\Api\Resources\Payment|null
     */
    public function getPayment($payment_id, $apiKey, $use_cache = true): ?\Mollie\Api\Resources\Payment
    {
        try {
            return $this->api_helper->getApiClient($apiKey)->payments->get($payment_id);
        } catch (\Mollie\Api\Exceptions\ApiException $apiException) {
            $this->logger->debug(__FUNCTION__ . sprintf(': Could not load payment %s (', $payment_id) . "): " . $apiException->getMessage() . ' (' . get_class($apiException) . ')');
        }

        return null;
    }

    /**
     * @param bool $test_mode
     * @param bool $use_cache
     *
     * @return array|mixed|\Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection
     */
    public function getAllPaymentMethods($apiKey, $test_mode = false, $use_cache = true)
    {
        $result = $this->getRegularPaymentMethods($apiKey, $test_mode, $use_cache);
        if (!is_array($result)) {
            $result = unserialize($result);
        }

        $isSubscriptionPluginActive = $this->isSubscriptionPluginActive();
        if ($isSubscriptionPluginActive) {
            $result = $this->addRecurringPaymentMethods($apiKey, $test_mode, $use_cache, $result);
        }

        return $result;
    }

    public function wooCommerceFiltersForCheckout(): array
    {

        $cart = WC()->cart;
        $cartTotal = $cart ? $cart->get_total('edit') : 0;

        $currency = get_woocommerce_currency();
        $customerExistsAndHasCountry = WC()->customer && !empty(WC()->customer->get_billing_country());
        $fallbackToShopCountry = wc_get_base_location()['country'];
        $billingCountry = $customerExistsAndHasCountry ? WC()->customer->get_billing_country() : $fallbackToShopCountry;

        $paymentLocale = $this->settingsHelper->getPaymentLocale();
        try {
            $filters = $this->getFilters(
                $currency,
                $cartTotal,
                $paymentLocale,
                $billingCountry
            );
        } catch (InvalidArgumentException $exception) {
            $filters = [];
        }

        return $filters;
    }
    /**
     * @param $order_total
     * @param $currency
     */
    protected function getAmountValue($order_total, $currency): string
    {
        return $this->formatCurrencyValue(
            $order_total,
            $currency
        );
    }

    /**
     * Returns a list of filters, ensuring that the values are valid.
     *
     * @param $currency
     * @param $orderTotal
     * @param $paymentLocale
     * @param $billingCountry
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getFilters(
        $currency,
        $orderTotal,
        $paymentLocale,
        $billingCountry
    ) {

        $amountValue = $this->getAmountValue($orderTotal, $currency);
        if ($amountValue <= 0) {
            throw new InvalidArgumentException(
                sprintf('Amount %s is not valid.', $amountValue)
            );
        }

        // Check if currency is in ISO 4217 alpha-3 format (ex: EUR)
        if (!preg_match('/^[a-zA-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(
                sprintf('Currency %s is not valid.', $currency)
            );
        }

        // Check if billing country is in ISO 3166-1 alpha-2 format (ex: NL)
        if (!preg_match('/^[a-zA-Z]{2}$/', $billingCountry)) {
            throw new InvalidArgumentException(
                sprintf('Billing Country %s is not valid.', $billingCountry)
            );
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
     * @param bool $test_mode
     * @param bool $use_cache
     *
     * @return array|mixed|\Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection
     */
    public function getRegularPaymentMethods($apiKey, $test_mode = false, $use_cache = true)
    {
        // Already initialized
        if ($use_cache && ! empty(self::$regular_api_methods)) {
            return self::$regular_api_methods;
        }

        self::$regular_api_methods = $this->getApiPaymentMethods($use_cache);

        return self::$regular_api_methods;
    }

    public function getRecurringPaymentMethods($apiKey, $test_mode = false, $use_cache = true)
    {
        // Already initialized
        if ($use_cache && ! empty(self::$recurring_api_methods)) {
            return self::$recurring_api_methods;
        }

        self::$recurring_api_methods = $this->getApiPaymentMethods($use_cache, [ 'sequenceType' => 'recurring' ]);

        return self::$recurring_api_methods;
    }

    public function getApiPaymentMethods($use_cache = true, $filters = [])
    {
        $test_mode = $this->isTestModeEnabled();
        $apiKey = $this->settingsHelper->getApiKey();

        $methods = false;

        $filters_key = $filters;
        $filters_key['mode'] = ( $test_mode ? 'test' : 'live' );
        $filters_key['api'] = 'methods';
        $transient_id = $this->getTransientId(md5(http_build_query($filters_key)));

        try {
            if ($use_cache) {
                // When no cache exists $methods will be `false`
                $methods =  get_transient($transient_id);
            } else {
                delete_transient($transient_id);
            }

            // No cache exists, call the API and cache the result
            if ($methods === false) {
                $filters['resource'] = 'orders';
                $filters['includeWallets'] = 'applepay';
                $filters['include'] = 'issuers';
                if (!$apiKey) {
                    return [];
                }
                $methods = $this->api_helper->getApiClient($apiKey)->methods->allActive($filters);

                $methods_cleaned = [];

                foreach ($methods as $method) {
                    $public_properties = get_object_vars($method); // get only the public properties of the object
                    $methods_cleaned[] = $public_properties;
                }

                // $methods_cleaned is empty array when the API doesn't return any methods, cache the empty array
                $methods = $methods_cleaned;

                // Set new transients (as cache)
                if ($use_cache) {
                    set_transient($transient_id, $methods, HOUR_IN_SECONDS);
                }
            }

            return $methods;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            /**
             * Cache the result for a short period
             * to prevent hammering the API with requests that are likely to fail again
             */
            if ($use_cache) {
                set_transient($transient_id, [], 60 * 5);
            }
            $this->logger->debug(__FUNCTION__ . ": Could not load Mollie methods (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')');

            return [];
        }
    }

    /**
     * @param bool $test_mode
     * @param      $method
     *
     * @return mixed|\Mollie\Api\Resources\Method|null
     */
    public function getPaymentMethod($method)
    {
        $test_mode = $this->isTestModeEnabled();
        $apiKey = $this->settingsHelper->getApiKey();

        $payment_methods = $this->getAllPaymentMethods($apiKey, $test_mode);

        foreach ($payment_methods as $payment_method) {
            if ($payment_method['id'] === $method) {
                return $payment_method;
            }
        }

        return null;
    }

    /**
     * Get issuers for payment method (e.g. for iDEAL, KBC/CBC payment button, gift cards)
     *
     * @param bool        $test_mode (default: false)
     * @param string|null $methodId
     *
     * @return array
     */
    public function getMethodIssuers($apiKey, $test_mode = false, $methodId = null)
    {
        try {
            $transient_id = $this->getTransientId($methodId . '_issuers_' . ($test_mode ? 'test' : 'live'));

            // When no cache exists $issuers will be `false`
            $issuers = get_transient($transient_id);
            if (is_array($issuers)) {
                return $issuers;
            }

            $method = $this->getMethodWithIssuersById($methodId, $apiKey);
            is_object($method) && $method = get_object_vars($method);
            $issuers = $method ? $method['issuers'] : [];
            set_transient($transient_id, $issuers, HOUR_IN_SECONDS);
            return $issuers;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            $this->logger->debug(__FUNCTION__ . ": Could not load " . $methodId . " issuers (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return  [];
    }

    /**
     * Take the method by Id from cache or call the API
     *
     * @param string $methodId
     * @param string $apiKey
     * @return bool|Method
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMethodWithIssuersById($methodId, $apiKey)
    {
        $method = $this->getCachedMethodById($methodId);
        if ($method) {
            return $method;
        }
        if (!$apiKey) {
            return false;
        }
        return $this->api_helper->getApiClient($apiKey)->methods->get(sprintf('%s', $methodId), [ "include" => "issuers" ]);
    }

    /**
     *
     * @param string $methodId
     * @return false|Method
     */
    public function getCachedMethodById(string $methodId)
    {
        $apiKey = $this->settingsHelper->getApiKey();
        $cachedMethods = $this->getRegularPaymentMethods($apiKey);
        if (empty($cachedMethods)) {
            return false;
        }
        foreach ($cachedMethods as $cachedMethod) {
            if ($cachedMethod['id'] !== $methodId) {
                continue;
            }
            return $cachedMethod;
        }
        return false;
    }

    /**
     * @param int         $user_id
     * @param string|null $customer_id
     *
     * @return $this
     */
    public function setUserMollieCustomerId($user_id, $customer_id)
    {
        if (! empty($customer_id)) {
            try {
                $customer = new WC_Customer($user_id);
                $customer->update_meta_data('mollie_customer_id', $customer_id);
                $customer->save();
                $this->logger->debug(__FUNCTION__ . ": Stored Mollie customer ID " . $customer_id . " with user " . $user_id);
            } catch (Exception $exception) {
                $this->logger->debug(__FUNCTION__ . ": Couldn't load (and save) WooCommerce customer based on user ID " . $user_id);
            }
        }

        return $this;
    }

    /**
     * @param $orderId
     * @param $customer_id
     * @return $this
     */
    public function setUserMollieCustomerIdAtSubscription($orderId, $customer_id)
    {
        if (!empty($customer_id)) {
            $order = wc_get_order($orderId);
            $order->update_meta_data('_mollie_customer_id', $customer_id);
            $order->save();
        }

        return $this;
    }

    /**
     * @param int  $user_id
     * @param bool $test_mode
     * @return null|string
     */
    public function getUserMollieCustomerId($user_id, $apiKey)
    {
        // Guest users can't buy subscriptions and don't need a Mollie customer ID
        // https://github.com/mollie/WooCommerce/issues/132
        if (empty($user_id)) {
            return null;
        }
        $isTestModeEnabled = $this->isTestModeEnabled();

        $customer = new WC_Customer($user_id);
        $customer_id = $customer->get_meta('mollie_customer_id');

        // If there is no Mollie Customer ID set, check the most recent active subscription
        if (empty($customer_id)) {
                $customer_latest_subscription = wc_get_orders([
                    'limit' => 1,
                    'customer' => $user_id,
                    'type' => 'shop_subscription',
                    'status' => 'wc-active',
                ]);

            if (! empty($customer_latest_subscription)) {
                $customer_id = get_post_meta($customer_latest_subscription[0]->get_id(), '_mollie_customer_id', $single = true);

                // Store this customer ID as user meta too
                $this->setUserMollieCustomerId($user_id, $customer_id);
            }
        }

        // If there is a Mollie Customer ID set, check that customer ID is valid for this API key
        if (! empty($customer_id)) {
            try {
                $this->api_helper->getApiClient($apiKey)->customers->get($customer_id);
            } catch (\Mollie\Api\Exceptions\ApiException $e) {
                $this->logger->debug(__FUNCTION__ . sprintf(': Mollie Customer ID (%s) not valid for user %s on this API key, try to create a new one (', $customer_id, $user_id) . ( $isTestModeEnabled ? 'test' : 'live' ) . ").");
                $customer_id = '';
            }
        }

        // If there is no Mollie Customer ID set, try to create a new Mollie Customer
        if (empty($customer_id)) {
            try {
                $userdata = get_userdata($user_id);

                // Get the best name for use as Mollie Customer name
                $user_full_name = $userdata->first_name . ' ' . $userdata->last_name;

                if (strlen(trim($user_full_name)) === null) {
                    $user_full_name = $userdata->display_name;
                }

                // Create the Mollie Customer
                $customer = $this->api_helper->getApiClient($apiKey)->customers->create([
                    'name' => trim($user_full_name),
                    'email' => trim($userdata->user_email),
                    'metadata' =>  [ 'user_id' => $user_id ],
                ]);

                $this->setUserMollieCustomerId($user_id, $customer->id);

                $customer_id = $customer->id;

                $this->logger->debug(__FUNCTION__ . sprintf(': Created a Mollie Customer (%s) for WordPress user with ID %s (', $customer_id, $user_id) . ( $isTestModeEnabled ? 'test' : 'live' ) . ").");

                return $customer_id;
            } catch (\Mollie\Api\Exceptions\ApiException $e) {
                $this->logger->debug(__FUNCTION__ . sprintf(': Could not create Mollie Customer for WordPress user with ID %s (', $user_id) . ( $isTestModeEnabled ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
            }
        } else {
            $this->logger->debug(__FUNCTION__ . sprintf(': Mollie Customer ID (%s) found and valid for user %s on this API key. (', $customer_id, $user_id) . ( $isTestModeEnabled ? 'test' : 'live' ) . ").");
        }

        return $customer_id;
    }

    /**
     * Get active Mollie payment mode for order
     *
     * @param int $order_id
     * @return string test or live
     */
    public function getActiveMolliePaymentMode($order_id)
    {
        $order = wc_get_order($order_id);

        return $order->get_meta('_mollie_payment_mode', true);
    }

    /**
     * @param WC_Order $order
     */
    public function restoreOrderStock(WC_Order $order)
    {
        wc_maybe_increase_stock_levels($order->get_id());
    }

    /**
     * Get the WooCommerce currency for current order
     *
     * @param $order
     *
     * @return string $value
     */
    public function getOrderCurrency($order)
    {
        return $order->get_currency();
    }

    /**
     * Format currency value into Mollie API v2 format
     *
     * @param float|string $value
     *
     * @return string
     */
    public function formatCurrencyValue($value, $currency)
    {
        return mollieWooCommerceFormatCurrencyValue($value, $currency);
    }

    /**
     *
     * @param  $orderId
     *
     * @return bool
     */
    public function isWcSubscription($orderId): bool
    {
        if (!(class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin'))) {
            return false;
        }

        if (
            function_exists('wcs_order_contains_subscription')
            && (wcs_order_contains_subscription($orderId)
                || function_exists('wcs_is_subscription')
                && wcs_is_subscription($orderId)
                || function_exists('wcs_order_contains_renewal')
                && wcs_order_contains_renewal($orderId))
        ) {
            return true;
        }
        return false;
    }

    public function isSubscription($orderId)
    {
        $isSubscription = false;
        return apply_filters($this->pluginId . '_is_subscription_payment', $isSubscription, $orderId);
    }

    public function getAllAvailablePaymentMethods($use_cache = true)
    {
        $apiKey = $this->settingsHelper->getApiKey();
        $methods = false;
        $transient_id = $this->getTransientId(md5('mollie_available_methods'));

        try {
            if ($use_cache) {
                // When no cache exists $methods will be `false`
                $methods =  get_transient($transient_id);
            } else {
                delete_transient($transient_id);
            }

            // No cache exists, call the API and cache the result
            if ($methods === false) {
                if (!$apiKey) {
                    return [];
                }
                $methods = $this->api_helper->getApiClient($apiKey)->methods->allAvailable();

                $methods_cleaned = [];

                foreach ($methods as $method) {
                    $public_properties = get_object_vars($method); // get only the public properties of the object
                    $methods_cleaned[] = $public_properties;
                }

                // $methods_cleaned is empty array when the API doesn't return any methods, cache the empty array
                $methods = $methods_cleaned;

                // Set new transients (as cache)
                if ($use_cache) {
                    set_transient($transient_id, $methods, HOUR_IN_SECONDS);
                }
            }

            return $methods;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            /**
             * Cache the result for a short period
             * to prevent hammering the API with requests that are likely to fail again
             */
            if ($use_cache) {
                set_transient($transient_id, [], 60 * 5);
            }
            $this->logger->debug(__FUNCTION__ . ": Could not load Mollie all available methods");

            return [];
        }
    }

    /**
     * @param $apiKey
     * @param bool $test_mode
     * @param bool $use_cache
     * @param $result
     * @return mixed
     */
    protected function addRecurringPaymentMethods($apiKey, bool $test_mode, bool $use_cache, $result)
    {
        $recurringPaymentMethods = $this->getRecurringPaymentMethods($apiKey, $test_mode, $use_cache);
        if (!is_array($recurringPaymentMethods)) {
            $recurringPaymentMethods = unserialize($recurringPaymentMethods);
        }
        foreach ($recurringPaymentMethods as $recurringItem) {
            $notFound = true;
            foreach ($result as $item) {
                if ($item['id'] === $recurringItem['id']) {
                    $notFound = false;
                    break;
                }
            }
            if ($notFound) {
                $result[] = $recurringItem;
            }
        }
        return $result;
    }
}
