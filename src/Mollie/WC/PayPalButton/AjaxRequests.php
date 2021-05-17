<?php

class Mollie_WC_PayPalButton_AjaxRequests
{

    /**
     * Adds all the Ajax actions to perform the whole workflow
     */
    public function bootstrapAjaxRequest()
    {

        add_action(
            'wp_ajax_' . Mollie_WC_PayPalButton_PropertiesDictionary::CREATE_ORDER,
            array($this, 'createWcOrder')
        );
        add_action(
            'wp_ajax_nopriv_' . Mollie_WC_PayPalButton_PropertiesDictionary::CREATE_ORDER,
            array($this, 'createWcOrder')
        );
        add_action(
            'wp_ajax_' . Mollie_WC_PayPalButton_PropertiesDictionary::CREATE_ORDER_CART,
            array($this, 'createWcOrderFromCart')
        );
        add_action(
            'wp_ajax_nopriv_' . Mollie_WC_PayPalButton_PropertiesDictionary::CREATE_ORDER_CART,
            array($this, 'createWcOrderFromCart')
        );
        add_action(
            'wp_ajax_' . Mollie_WC_PayPalButton_PropertiesDictionary::UPDATE_AMOUNT,
            array($this, 'updateAmount')
        );
        add_action(
            'wp_ajax_nopriv_' . Mollie_WC_PayPalButton_PropertiesDictionary::UPDATE_AMOUNT,
            array($this, 'updateAmount')
        );

    }

    /**
     * Creates the order from the product detail page and process the payment
     * On error returns an array of errors to be handled by the script
     * On success returns the status success
     * and the url to redirect the user
     *
     * @throws WC_Data_Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createWcOrder()
    {
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData($_POST, 'productDetail');
        if (!$this->isNonceValid($payPalRequestDataObject)) {
            return;
        }

        $order = wc_create_order();
        $order->add_product(
            wc_get_product($payPalRequestDataObject->productId),
            $payPalRequestDataObject->productQuantity
        );

        $surchargeHandler = new Mollie_WC_Helper_GatewaySurchargeHandler();
        $order = $surchargeHandler->addSurchargeFeeProductPage($order, 'mollie_wc_gateway_paypal');

        $orderId = $order->get_id();
        $order->calculate_totals();
        $this->updateOrderPostMeta($orderId, $order);

        $result = $this->processOrderPayment($orderId);

        if (isset($result['result'])
            && 'success' === $result['result']
        ) {
            wp_send_json_success($result);
        } else {
            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'PayPal'
            );

            mollieWooCommerceDebug($message, 'error');
            wp_send_json_error($message);
        }
    }

    /**
     * Creates the order from the cart page and process the payment
     * On error returns an array of errors to be handled by the script
     * On success returns the status success
     * and the url to redirect the user
     *
     * @throws WC_Data_Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createWcOrderFromCart()
    {
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData($_POST, 'cart');
        if (!$this->isNonceValid($payPalRequestDataObject)) {
            return;
        }

        list($cart, $order) = $this->createOrderFromCart();
        $orderId = $order->get_id();
        $order->calculate_totals();
        $surchargeHandler = new Mollie_WC_Helper_GatewaySurchargeHandler();
        $order = $surchargeHandler->addSurchargeFeeProductPage($order, 'mollie_wc_gateway_paypal');
        $this->updateOrderPostMeta($orderId, $order);
        $result = $this->processOrderPayment($orderId);
        if (isset($result['result'])
            && 'success' === $result['result']
        ) {
            $cart->empty_cart();
            wp_send_json_success($result);
        } else {
            /* translators: Placeholder 1: Payment method title */
            $message = sprintf(
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'PayPal'
            );

            Mollie_WC_Plugin::addNotice($message, 'error');
            wp_send_json_error($message);
        }
    }

    public function updateAmount(){
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData($_POST, 'productDetail');

        if (!$this->isNonceValid($payPalRequestDataObject)) {
            wp_send_json_error('no nonce');
        }

        $order = new Mollie_WC_PayPalButton_WCOrderCalculator();
        $order->set_currency( get_woocommerce_currency() );
        $order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
        $order->add_product(
            wc_get_product($payPalRequestDataObject->productId),
            $payPalRequestDataObject->productQuantity
        );

        $updatedAmount = $order->calculate_totals();

        wp_send_json_success($updatedAmount);
    }



    /**
     * Data Object to collect and validate all needed data collected
     * through HTTP
     *
     * @return Mollie_WC_PayPalButton_PayPalDataObjectHttp
     */
    protected function PayPalDataObjectHttp()
    {
        return new Mollie_WC_PayPalButton_PayPalDataObjectHttp();
    }

    /**
     * Update order post meta
     *
     * @param string $orderId
     * @param        $order
     */
    protected function updateOrderPostMeta($orderId, $order)
    {
        update_post_meta($orderId, '_customer_user', get_current_user_id());
        update_post_meta(
            $orderId,
            '_payment_method',
            'mollie_wc_gateway_paypal'
        );
        update_post_meta($orderId, '_payment_method_title', 'PayPal');
        $order->update_status(
            'Processing',
            'PayPal Button order',
            true
        );
    }

    /**
     * Process order payment with PayPal gateway
     *
     * @param int $orderId
     *
     * @return array|string[]
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    protected function processOrderPayment($orderId)
    {
        $gateway = new Mollie_WC_Gateway_PayPal();

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
     * Checks if the nonce in the data object is valid
     *
     * @param Mollie_WC_PayPalButton_PayPalDataObjectHttp $PayPalRequestDataObject
     *
     * @return bool|int
     */
    protected function isNonceValid(
        Mollie_WC_PayPalButton_PayPalDataObjectHttp $PayPalRequestDataObject
    ) {
        $isNonceValid = wp_verify_nonce(
            $PayPalRequestDataObject->nonce,
            'mollie_PayPal_button'
        );
        return $isNonceValid;
    }

}
