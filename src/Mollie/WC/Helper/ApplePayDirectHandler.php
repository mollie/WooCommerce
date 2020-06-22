<?php

class Mollie_WC_Helper_ApplePayDirectHandler
{
    const VALIDATION = 'mollie_apple_pay_validation';
    const CREATE_ORDER = 'mollie_apple_pay_create_order';
    const CREATE_ORDER_CART = 'mollie_apple_pay_create_order_cart';
    const UPDATE_SHIPPING_CONTACT = 'mollie_apple_pay_update_shipping_contact';
    const UPDATE_SHIPPING_METHOD = 'mollie_apple_pay_update_shipping_method';
    const REDIRECT = 'mollie_apple_pay_redirect';

    /**
     * Initial method that checks if the device is compatible
     * if so puts the button in place
     * and adds all the necessary actions
     */
    public function bootstrap()
    {
        if (!$this->isApplePayCompatible()) {
            add_action(
                'admin_notices',
                function () {
                    echo '<div class="error"><p>';
                    echo sprintf(
                        esc_html__(
                            '%1$sServer not compliant with Apple requirements%2$s Check %3$sApple Server requirements page%4$s to fix it in order to make the Apple Pay button work',
                            'mollie-payments-for-woocommerce'
                        ),
                        '<strong>',
                        '</strong>',
                        '<a href="https://developer.apple.com/documentation/apple_pay_on_the_web/setting_up_your_server">',
                        '</a>'
                    );
                    echo '</p></div>';
                    return false;
                }
            );

            return;
        }

        if (!$this->merchantValidated()) {
            add_action(
                'admin_notices',
                function () {
                    echo '<div class="error"><p>';
                    echo sprintf(
                        esc_html__(
                            '%1$sApple Pay Validation Error%2$s Check %3$sApple Server requirements page%4$s to fix it in order to make the Apple Pay button work',
                            'mollie-payments-for-woocommerce'
                        ),
                        '<strong>',
                        '</strong>',
                        '<a href="https://developer.apple.com/documentation/apple_pay_on_the_web/setting_up_your_server">',
                        '</a>'
                    );
                    echo '</p></div>';
                    return false;
                }
            );
        }
        add_action(
            'woocommerce_after_add_to_cart_form',
            function () {
                $this->applePayDirectButton();
            }
        );
        add_action(
            'woocommerce_cart_totals_after_order_total',
            function () {
                $this->applePayDirectButton();
            }
        );
        admin_url('admin-ajax.php');
        $this->bootstrapAjaxRequest();
    }

    /**
     * Checks if the server is HTTPS
     *
     * @return bool
     */
    private function isApplePayCompatible()
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
    }

    /**
     * Sets the appropriate data to send to ApplePay script
     * Data differs between product page and cart page
     *
     * @return array|bool
     */
    public function applePayScriptData()
    {
        $base_location = wc_get_base_location();
        $shopCountryCode = $base_location['country'];
        $currencyCode = get_woocommerce_currency();
        $totalLabel = get_bloginfo('name');
        if (is_product()) {
            $product = wc_get_product(get_the_id());
            if (!$product) {
                return false;
            }
            $isVariation = false;
            if ($product->get_type() === 'variable') {
                $isVariation = true;
            }
            $productNeedShipping = $this->checkIfNeedShipping($product);
            $productId = get_the_id();
            $productPrice = $product->get_price();

            return [
                'product' => [
                    'needShipping' => $productNeedShipping,
                    'id' => $productId,
                    'price' => $productPrice,
                    'isVariation' => $isVariation,
                ],
                'shop' => [
                    'countryCode' => $shopCountryCode,
                    'currencyCode' => $currencyCode,
                    'totalLabel' => $totalLabel
                ],
                'ajaxUrl' => admin_url('admin-ajax.php')
            ];
        }
        if (is_cart()) {
            $cart = WC()->cart;

            return [
                'product' => [
                    'needShipping' => $cart->needs_shipping(),
                    'subtotal' => $cart->get_subtotal(),

                ],
                'shop' => [
                    'countryCode' => $shopCountryCode,
                    'currencyCode' => $currencyCode,
                    'totalLabel' => $totalLabel
                ],
                'ajaxUrl' => admin_url('admin-ajax.php')
            ];
        }
        return [];
    }

    /**
     * Method to validate the merchant against Apple system through Mollie
     * On fail triggers and option that shows an admin notice showing the error
     * On success returns the validation data to the script
     */
    public function validateMerchant()
    {
        $applePayRequestDataObject = $this->applePayDataObjectHttp();
        $applePayRequestDataObject->validationData($_POST);

        if (!$this->isNonceValid($applePayRequestDataObject)) {
            wp_send_json_error('no nonce');
        }
        $validationUrl = $applePayRequestDataObject->validationUrl;
        $completeDomain = get_site_url();
        $removeHttp = ["https://", "http://", "/"];
        $domain = str_replace($removeHttp, "", $completeDomain);

        try {
            $json = $this->validationApiWalletsEndpointCall(
                $domain,
                $validationUrl
            );
        } catch (\Mollie\Api\Exceptions\ApiException $exc) {
            update_option('g_test', $exc->getMessage());
            update_option('mollie_wc_applepay_validated', 'no');
            wp_send_json_error($exc->getMessage());
        }

        update_option('mollie_wc_applepay_validated', 'yes');
        update_option('mollie_apple_pay_session_response', $json);

        wp_send_json_success($json);
    }

    /**
     * Method to validate and update the shipping contact of the user
     * It updates the amount paying information if needed
     * On error returns an array of errors to be handled by the script
     * On success returns the new contact data
     */
    public function updateShippingContact()
    {
        $applePayRequestDataObject = $this->applePayDataObjectHttp();
        $applePayRequestDataObject->updateContactData($_POST);

        if (!$this->isNonceValid($applePayRequestDataObject)) {
            return;
        }
        if ($applePayRequestDataObject->hasErrors()) {
            $this->responseWithDataErrors($applePayRequestDataObject->errors);
            return;
        }

        if (class_exists('WC_Countries')) {
            $countries = $this->createWCCountries();
            $allowedSellingCountries = $countries->get_allowed_countries();
            $allowedShippingCountries = $countries->get_shipping_countries();
            $isAllowedSellingCountry = array_key_exists(
                $applePayRequestDataObject->simplifiedContact['country'],
                $allowedSellingCountries
            );

            $isAllowedShippingCountry = array_key_exists(
                $applePayRequestDataObject->simplifiedContact['country'],
                $allowedShippingCountries
            );
            $productNeedShipping
                = $applePayRequestDataObject->needShipping;
            if (!$isAllowedSellingCountry) {
                $this->responseWithDataErrors(
                    [['errorCode' => 'addressUnserviceable']]
                );
                return;
            }
            if ($productNeedShipping && !$isAllowedShippingCountry) {
                $this->responseWithDataErrors(
                    [['errorCode' => 'addressUnserviceable']]
                );
                return;
            }
        }
        $paymentDetails = $this->whichCalculateTotals($applePayRequestDataObject);
        $response = $this->appleFormattedResponse($paymentDetails);

        $this->responseSuccess($response);
    }

    /**
     ** Method to validate and update the shipping method selected by the user
     * It updates the amount paying information if needed
     * On error returns an array of errors to be handled by the script
     * On success returns the new contact data
     */
    public function updateShippingMethod()
    {
        $applePayRequestDataObject = $this->applePayDataObjectHttp();
        $applePayRequestDataObject->updateMethodData($_POST);

        if (!$this->isNonceValid($applePayRequestDataObject)) {
            return;
        }
        if ($applePayRequestDataObject->hasErrors()) {
            $this->responseWithDataErrors($applePayRequestDataObject->errors);
        }
        $paymentDetails = $this->whichCalculateTotals($applePayRequestDataObject);
        $response = $this->appleFormattedResponse($paymentDetails);
        $this->responseSuccess($response);
    }

    /**
     * Creates the order from the product detail page and process the payment
     * On error returns an array of errors to be handled by the script
     * On success returns the status success for Apple to close the transaction
     * and the url to redirect the user
     *
     * @throws WC_Data_Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createWcOrder()
    {
        $applePayRequestDataObject = $this->applePayDataObjectHttp();
        $applePayRequestDataObject->orderData($_POST, 'productDetail');
        if (!$this->isNonceValid($applePayRequestDataObject)) {
            return;
        }
        if ($applePayRequestDataObject->hasErrors()) {
            $this->responseWithDataErrors($applePayRequestDataObject->errors);
        }
        $order = wc_create_order();
        $order->add_product(
            wc_get_product($applePayRequestDataObject->productId),
            $applePayRequestDataObject->productQuantity
        );
        $order = $this->addAddressesToOrder($applePayRequestDataObject, $order);

        $order = $this->addShippingMethodsToOrder(
            $applePayRequestDataObject->shippingMethod,
            $applePayRequestDataObject->shippingAddress,
            $order
        );

        $orderId = $order->get_id();

        $order->calculate_totals();

        $this->updateOrderPostMeta($orderId, $order);
        $result = $this->processOrderPayment($orderId);

        if (isset($result['result'])
            && 'success' === $result['result']
        ) {
            $order->payment_complete();

            $this->responseSuccess(
                $this->authorizationResultResponse(
                    'STATUS_SUCCESS',
                    $orderId
                )
            );
        } else {
            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'ApplePay'
            );

            mollieWooCommerceDebug($message, 'error');
            wp_send_json_error(
                $this->authorizationResultResponse('STATUS_FAILURE')
            );
        }
    }

    /**
     * Creates the order from the cart page and process the payment
     * On error returns an array of errors to be handled by the script
     * On success returns the status success for Apple to close the transaction
     * and the url to redirect the user
     *
     * @throws WC_Data_Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createWcOrderFromCart()
    {
        $applePayRequestDataObject = $this->applePayDataObjectHttp();
        $applePayRequestDataObject->orderData($_POST, 'cart');
        if (!$this->isNonceValid($applePayRequestDataObject)) {
            return;
        }

        list($cart, $order) = $this->createOrderFromCart();
        $order = $this->addAddressesToOrder($applePayRequestDataObject, $order);
        $order = $this->addShippingMethodsToOrder(
            $applePayRequestDataObject->shippingMethod,
            $applePayRequestDataObject->shippingAddress,
            $order
        );
        $orderId = $order->get_id();
        $order->calculate_totals();
        $this->updateOrderPostMeta($orderId, $order);
        $result = $this->processOrderPayment($orderId);
        if (isset($result['result'])
            && 'success' === $result['result']
        ) {
            $order->payment_complete();
            $cart->empty_cart();
            $this->responseSuccess(
                $this->authorizationResultResponse(
                    'STATUS_SUCCESS',
                    $orderId
                )
            );
        } else {
            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'ApplePay'
            );

            Mollie_WC_Plugin::addNotice($message, 'error');

            wp_send_json_error(
                $this->authorizationResultResponse(
                    'STATUS_FAILURE',
                    0,
                    [['errorCode' => 'unknown']]
                )
            );
        }
    }

    /**
     * Returns the redirect url to use on successful payment
     *
     * @param $orderId
     *
     * @return string
     */
    public function redirectOnApplePaySuccessfulPayment($orderId)
    {
        $gateway = new Mollie_WC_Gateway_Applepay();
        $order = wc_get_order($orderId);
        $redirect_url = $gateway->getReturnRedirectUrlForOrder($order);
        // Add utm_nooverride query string
        $redirect_url = add_query_arg(['utm_nooverride' => 1], $redirect_url);
        mollieWooCommerceDebug(
            __METHOD__
            . ": Redirect url on return order {$gateway->id}, order {$orderId}: {$redirect_url}"
        );

        return $redirect_url;
    }

    /**
     * Checks if the merchant has been validated
     *
     * @return bool
     */
    protected function merchantValidated()
    {
        $option = get_option('mollie_wc_applepay_validated', 'yes');

        return $option == 'yes';
    }

    /**
     * ApplePay button markup
     */
    protected function applePayDirectButton()
    {
        ?>
        <div id="mollie-applepayDirect-button">
            <?php wp_nonce_field('mollie_applepay_button'); ?>
        </div>
        <?php
    }

    /**
     * Adds all the Ajax actions to perform the whole workflow
     */
    protected function bootstrapAjaxRequest()
    {
        add_action(
            'wp_ajax_' . self::VALIDATION,
            array($this, 'validateMerchant')
        );
        add_action(
            'wp_ajax_nopriv_' . self::VALIDATION,
            array($this, 'validateMerchant')
        );
        add_action(
            'wp_ajax_' . self::CREATE_ORDER,
            array($this, 'createWcOrder')
        );
        add_action(
            'wp_ajax_nopriv_' . self::CREATE_ORDER,
            array($this, 'createWcOrder')
        );
        add_action(
            'wp_ajax_' . self::CREATE_ORDER_CART,
            array($this, 'createWcOrderFromCart')
        );
        add_action(
            'wp_ajax_nopriv_' . self::CREATE_ORDER_CART,
            array($this, 'createWcOrderFromCart')
        );
        add_action(
            'wp_ajax_' . self::UPDATE_SHIPPING_CONTACT,
            array($this, 'updateShippingContact')
        );
        add_action(
            'wp_ajax_nopriv_' . self::UPDATE_SHIPPING_CONTACT,
            array($this, 'updateShippingContact')
        );
        add_action(
            'wp_ajax_' . self::UPDATE_SHIPPING_METHOD,
            array($this, 'updateShippingMethod')
        );
        add_action(
            'wp_ajax_nopriv_' . self::UPDATE_SHIPPING_METHOD,
            array($this, 'updateShippingMethod')
        );
        add_action(
            'wp_ajax_' . self::REDIRECT,
            array($this, 'redirectOnApplePaySuccessfulPayment')
        );
        add_action(
            'wp_ajax_nopriv_' . self::REDIRECT,
            array($this, 'redirectOnApplePaySuccessfulPayment')
        );
    }

    /**
     * Check if the product needs shipping
     *
     * @param $product
     *
     * @return bool
     */
    protected function checkIfNeedShipping($product)
    {
        if (!wc_shipping_enabled()
            || 0 === wc_get_shipping_method_count(
                true
            )
        ) {
            return false;
        }
        $needs_shipping = false;

        if ($product->needs_shipping()) {
            $needs_shipping = true;
        }

        return $needs_shipping;
    }

    /**
     * Data Object to collect and validate all needed data collected
     * through HTTP
     *
     * @return Mollie_WC_Helper_ApplePayDataObjectHttp
     */
    protected function applePayDataObjectHttp()
    {
        return new Mollie_WC_Helper_ApplePayDataObjectHttp();
    }

    /**
     * Checks if the nonce in the data object is valid
     *
     * @param Mollie_WC_Helper_ApplePayDataObjectHttp $applePayRequestDataObject
     *
     * @return bool|int
     */
    protected function isNonceValid(
        Mollie_WC_Helper_ApplePayDataObjectHttp $applePayRequestDataObject
    ) {
        $isNonceValid = wp_verify_nonce(
            $applePayRequestDataObject->nonce,
            'mollie_applepay_button'
        );
        return $isNonceValid;
    }

    /**
     * Calls Mollie API wallets to validate merchant session
     *
     * @param string $domain
     * @param        $validationUrl
     *
     * @return false|string
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    protected function validationApiWalletsEndpointCall(
        $domain,
        $validationUrl
    ) {
        $json = Mollie_WC_Plugin::getApiHelper()
            ->getApiClient()
            ->wallets
            ->requestApplePayPaymentSession(
                $domain,
                $validationUrl
            );
        return $json;
    }

    /**
     * Returns a WC_Countries instance to check shipping
     *
     * @return WC_Countries
     */
    protected function createWCCountries()
    {
        $countries = new WC_Countries();
        return $countries;
    }

    /**
     * Selector between product detail and cart page calculations
     *
     * @param $applePayRequestDataObject
     *
     * @return array|bool
     */
    protected function whichCalculateTotals(
        $applePayRequestDataObject
    ) {
        if ($applePayRequestDataObject->callerPage === 'productDetail') {
            return $this->calculateTotalsSingleProduct(
                $applePayRequestDataObject->productId,
                $applePayRequestDataObject->productQuantity,
                $applePayRequestDataObject->simplifiedContact,
                $applePayRequestDataObject->shippingMethod
            );
        }
        if ($applePayRequestDataObject->callerPage === 'cart') {
            return $this->calculateTotalsCartPage(
                $applePayRequestDataObject->simplifiedContact,
                $applePayRequestDataObject->shippingMethod
            );
        }
        return false;
    }

    /**
     * Calculates totals for the product with the given information
     * Saves the previous cart to reload it after calculations
     * If no shippingMethodId provided will return the first available shipping
     * method
     *
     * @param      $productId
     * @param      $productQuantity
     * @param      $customerAddress
     * @param null $shippingMethod
     *
     * @return array
     */
    protected function calculateTotalsSingleProduct(
        $productId,
        $productQuantity,
        $customerAddress,
        $shippingMethod = null
    ) {
        $results = [];
        $reloadCart = false;
        if (!WC()->cart->is_empty()) {
            $oldCartContents = WC()->cart->get_cart_contents();
            foreach ($oldCartContents as $cartItemKey => $value) {
                WC()->cart->remove_cart_item($cartItemKey);
            }
            $reloadCart = true;
        }
        try {
            //I just care about apple address details
            $shippingMethodId = '';
            $shippingMethodsArray = [];
            $selectedShippingMethod = [];
            $this->customerAddress($customerAddress);
            $cart = WC()->cart;
            if ($shippingMethod) {
                $shippingMethodId = $shippingMethod['identifier'];
                WC()->session->set(
                    'chosen_shipping_methods',
                    array($shippingMethodId)
                );
            }
            $cartItemKey = $cart->add_to_cart($productId, $productQuantity);
            if ($cart->needs_shipping()) {
                list(
                    $shippingMethodsArray, $selectedShippingMethod
                    )
                    = $this->cartShippingMethods(
                    $cart,
                    $customerAddress,
                    $shippingMethod,
                    $shippingMethodId
                );
            }

            $cart->calculate_shipping();
            $cart->calculate_fees();
            $cart->calculate_totals();

            $results = $this->cartCalculationResults(
                $cart,
                $selectedShippingMethod,
                $shippingMethodsArray
            );

            $cart->remove_cart_item($cartItemKey);
            $this->customerAddress();
            if ($reloadCart) {
                foreach ($oldCartContents as $cartItemKey => $value) {
                    $cart->restore_cart_item($cartItemKey);
                }
            }
        } catch (Exception $e) {
        }


        return $results;
    }

    /**
     * Sets the customer address with ApplePay details to perform correct
     * calculations
     * If no parameter passed then it resets the customer to shop details
     *
     * @param array $address
     */
    protected function customerAddress($address = [])
    {
        $base_location = wc_get_base_location();
        $shopCountryCode = $base_location['country'];
        WC()->customer->set_shipping_country(
            $address['country'] ?: $shopCountryCode
        );
        WC()->customer->set_billing_country(
            $address['country'] ?: $shopCountryCode
        );
        WC()->customer->set_shipping_postcode(
            $address['postcode'] ?: ''
        );
        WC()->customer->set_shipping_city(
            $address['city'] ?: ''
        );
    }

    /**
     * Add shipping methods to cart to perform correct calculations
     *
     * @param WC_Cart $cart
     * @param         $customerAddress
     * @param         $shippingMethod
     * @param         $shippingMethodId
     *
     * @return array
     */
    protected function cartShippingMethods(
        WC_Cart $cart,
        $customerAddress,
        $shippingMethod,
        $shippingMethodId
    ) {
        $shippingMethodsArray = [];
        $shippingMethods = WC()->shipping->calculate_shipping(
            $this->getShippingPackages(
                $customerAddress,
                $cart->get_total('edit')
            )
        );
        $done = false;
        foreach ($shippingMethods[0]['rates'] as $rate) {
            array_push(
                $shippingMethodsArray,
                [
                    "label" => $rate->get_label(),
                    "detail" => "",
                    "amount" => $rate->get_cost(),
                    "identifier" => $rate->get_id()
                ]
            );
            if (!$done) {
                $done = true;
                $shippingMethodId = $shippingMethod ? $shippingMethodId
                    : $rate->get_id();
                WC()->session->set(
                    'chosen_shipping_methods',
                    array($shippingMethodId)
                );
            }
        }

        $selectedShippingMethod = $shippingMethodsArray[0];
        if ($shippingMethod) {
            $selectedShippingMethod = $shippingMethod;
        }

        return array($shippingMethodsArray, $selectedShippingMethod);
    }

    /**
     * Sets shipping packages for correct calculations
     * @param $customerAddress
     * @param $total
     *
     * @return mixed|void|null
     */
    protected function getShippingPackages($customerAddress, $total)
    {
        // Packages array for storing 'carts'
        $packages = array();
        $packages[0]['contents'] = WC()->cart->cart_contents;
        $packages[0]['contents_cost'] = $total;
        $packages[0]['applied_coupons'] = WC()->session->applied_coupon;
        $packages[0]['destination']['country'] = $customerAddress['country'];
        $packages[0]['destination']['state'] = '';
        $packages[0]['destination']['postcode'] = $customerAddress['postcode'];
        $packages[0]['destination']['city'] = $customerAddress['city'];
        $packages[0]['destination']['address'] = '';
        $packages[0]['destination']['address_2'] = '';


        return apply_filters('woocommerce_cart_shipping_packages', $packages);
    }

    /**
     * Returns the formatted results of the cart calculations
     *
     * @param WC_Cart $cart
     * @param         $selectedShippingMethod
     * @param         $shippingMethodsArray
     *
     * @return array
     */
    protected function cartCalculationResults(
        WC_Cart $cart,
        $selectedShippingMethod,
        $shippingMethodsArray
    ) {
        $results = [
            'subtotal' => $cart->get_subtotal(),
            'shipping' => [
                'amount' => $cart->needs_shipping()
                    ? $cart->get_shipping_total() : null,
                'label' => $cart->needs_shipping()
                    ? $selectedShippingMethod['label'] : null
            ],
            'shippingMethods' => $cart->needs_shipping()
                ? $shippingMethodsArray : null,
            'taxes' => $cart->get_total_tax(),
            'total' => $cart->get_total('edit')
        ];
        return $results;
    }

    /**
     * Calculates totals for the cart page with the given information
     * If no shippingMethodId provided will return the first available shipping
     * method
     *
     * @param      $customerAddress
     * @param null $shippingMethodId
     *
     * @return array
     */
    protected function calculateTotalsCartPage(
        $customerAddress = null,
        $shippingMethodId = null
    ) {
        $results = [];
        if (WC()->cart->is_empty()) {
            return [];
        }
        try {
            $shippingMethodsArray = [];
            $selectedShippingMethod = [];
            //I just care about apple address details
            $this->customerAddress($customerAddress);
            $cart = WC()->cart;
            if ($shippingMethodId) {
                WC()->session->set(
                    'chosen_shipping_methods',
                    array($shippingMethodId['identifier'])
                );
            }

            if ($cart->needs_shipping()) {
                list(
                    $shippingMethodsArray, $selectedShippingMethod
                    )
                    = $this->cartShippingMethods(
                    $cart,
                    $customerAddress,
                    $shippingMethodId,
                    $shippingMethodId['identifier']
                );
            }
            $cart->calculate_shipping();
            $cart->calculate_fees();
            $cart->calculate_totals();

            $results = $this->cartCalculationResults(
                $cart,
                $selectedShippingMethod,
                $shippingMethodsArray
            );

            $this->customerAddress();
        } catch (Exception $e) {
        }

        return $results;
    }

    /**
     * Add address billing and shipping data to order
     *
     * @param Mollie_WC_Helper_ApplePayDataObjectHttp $applePayRequestDataObject
     * @param                                         $order
     *
     * @return mixed
     */
    protected function addAddressesToOrder(
        Mollie_WC_Helper_ApplePayDataObjectHttp $applePayRequestDataObject,
        $order
    ) {
        $billingAddress = $applePayRequestDataObject->billingAddress;
        $shippingAddress = $applePayRequestDataObject->shippingAddress;
        //apple puts email in shippingAddress while we get it from WC's billingAddress
        $billingAddress['email'] = $shippingAddress['email'];
        $billingAddress['phone'] = $shippingAddress['phone'];

        $order->set_address($billingAddress, 'billing');
        $order->set_address($shippingAddress, 'shipping');
        return $order;
    }

    /**
     * Add shipping methods to order
     *
     * @param array $shippingMethod
     * @param array $shippingAddress
     * @param       $order
     *
     * @return mixed
     */
    protected function addShippingMethodsToOrder(
        array $shippingMethod,
        array $shippingAddress,
        $order
    ) {
        if ($shippingMethod) {
            $calculate_tax_for = array(
                'country' => $shippingAddress['country'],
                'state' => $shippingAddress['state'],
                'postcode' => $shippingAddress['postcode'],
                'city' => $shippingAddress['city'],
            );
            $item = new WC_Order_Item_Shipping();
            $ratesIds = explode(":", $shippingMethod['identifier']);
            $shippingMethodId = $ratesIds[0];
            $shippingInstanceId = $ratesIds[1];

            $item->set_props(
                array(
                    'method_title' => $shippingMethod['label'],
                    'method_id' => $shippingMethodId,
                    'instance_id' => $shippingInstanceId,
                    'total' => wc_format_decimal(
                        $shippingMethod['amount']
                    ),
                )
            );
            $item->calculate_taxes($calculate_tax_for);
            $order->add_item($item);
        }
        return $order;
    }

    /**
     * Update order post meta
     *
     * @param string $orderId
     * @param        $order
     */
    protected function updateOrderPostMeta($orderId, $order)
    {
//this is the logged one, if not logged in then create a new one?
        update_post_meta($orderId, '_customer_user', get_current_user_id());
        update_post_meta(
            $orderId,
            '_payment_method',
            'mollie_wc_gateway_applepay'
        );
        update_post_meta($orderId, '_payment_method_title', 'Apple Pay');
        $order->update_status(
            'Processing',
            'Apple Pay direct order',
            true
        );
    }

    /**
     * Process order payment with ApplePay gateway
     *
     * @param int $orderId
     *
     * @return array|string[]
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    protected function processOrderPayment($orderId)
    {
        $gateway = new Mollie_WC_Gateway_Applepay();

        $result = $gateway->process_payment($orderId);
        return $result;
    }

    /**
     * Handles the order creation in cart page
     *
     * @return array
     * @throws Exception
     */
    protected function createOrderFromCart()
    {
        $cart = WC()->cart;
        $checkout = WC()->checkout();
        $orderId = $checkout->create_order([]);
        $order = wc_get_order($orderId);
        return array($cart, $order);
    }

    /**
     * Returns an error response to be handled by the script
     *
     * @param array $errorList [['errorCode'=>required, 'contactField'=>'']]
     *
     * @return void
     */
    protected function responseWithDataErrors($errorList)
    {
        $response = [];
        $response['errors'] = $this->applePayError($errorList);
        $response['newTotal'] = $this->appleNewTotalResponse(
            0,
            'pending'
        );
        wp_send_json_error($response);
    }

    /**
     * Creates an array of errors formatted
     *
     * @param array $errorList
     * @param array $errors
     *
     * @return array
     */
    protected function applePayError($errorList, $errors = [])
    {
        foreach ($errorList as $error) {
            array_push(
                $errors,
                [
                    "code" => $error['errorCode'],
                    "contactField" => array_key_exists('contactField', $error)
                        ? $error['contactField'] : null,
                    "message" => array_key_exists('contactField', $error)
                        ? "Missing {$error['contactField']}" : "",
                ]
            );
        }

        return $errors;
    }

    /**
     * Creates NewTotals line
     *
     * @param        $total
     *
     * @param string $type
     *
     * @return array
     */
    protected function appleNewTotalResponse($total, $type = 'final')
    {
        return $this->appleItemFormat(
            get_bloginfo('name'),
            $total,
            $type
        );
    }

    /**
     * Creates item line
     *
     * @param $subtotalLabel
     * @param $subtotal
     * @param $type
     *
     * @return array
     */
    protected function appleItemFormat($subtotalLabel, $subtotal, $type)
    {
        return [
            "label" => $subtotalLabel,
            "amount" => $subtotal,
            "type" => $type
        ];
    }

    /**
     * Creates a response formatted for ApplePay
     *
     * @param array $paymentDetails
     *
     * @return array
     */
    protected function appleFormattedResponse(array $paymentDetails)
    {
        $response = [];
        if ($paymentDetails['shippingMethods']) {
            $response['newShippingMethods']
                = $paymentDetails['shippingMethods'];
        }

        $response['newLineItems'] = $this->appleNewLineItemsResponse(
            $paymentDetails
        );

        $response['newTotal'] = $this->appleNewTotalResponse(
            $paymentDetails['total']
        );
        return $response;
    }

    /**
     * Creates NewLineItems line
     *
     * @param array $paymentDetails
     *
     * @return array[]
     */
    protected function appleNewLineItemsResponse(array $paymentDetails)
    {
        $type = 'final';
        $response = [];
        array_push(
            $response,
            $this->appleItemFormat(
                'Subtotal',
                $paymentDetails['subtotal'],
                $type
            )
        );
        if ($paymentDetails['shipping']) {
            array_push(
                $response,
                $this->appleItemFormat(
                    $paymentDetails['shipping']['label'],
                    $paymentDetails['shipping']['amount'],
                    $type
                )
            );
        }
        array_push(
            $response,
            $this->appleItemFormat(
                'Estimated Tax',
                $paymentDetails['taxes'],
                $type
            )
        );
        return $response;
    }

    /**
     * Returns a success response to be handled by the script
     *
     * @param array $response
     */
    protected function responseSuccess(array $response)
    {
        wp_send_json_success($response);
    }

    /**
     * Returns the authorization response with according success/fail status
     * Adds the error list if provided to be handled by the script
     * On success it adds the redirection url
     *
     * @param        $status 0 => success, 1 => error
     * @param string $orderId
     * @param array  $errorList
     *
     * @return array
     */
    protected function authorizationResultResponse(
        $status,
        $orderId = '',
        $errorList = []
    ) {
        $response = [];
        if ($status === 'STATUS_SUCCESS') {
            $response['returnUrl'] = $this->redirectOnApplePaySuccessfulPayment(
                $orderId
            );
            $response['responseToApple'] = ['status' => 0];
        } else {
            $response = [
                'status' => 1,
                'errors' => $this->applePayError($errorList)
            ];
        }

        return $response;
    }


}

