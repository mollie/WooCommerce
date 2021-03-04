<?php

class Mollie_WC_PayPalButton_AjaxRequests
{


    /**
     * Mollie_WC_PayPalButton_AjaxRequests constructor.
     *
     *
     */
    public function __construct()
    {

    }

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

        $needsShipping
            = $payPalRequestDataObject->needShipping === 'true';
        if ($needsShipping) {
            $order = $this->addShippingMethodsToOrder(
                $order
            );
        }

        $orderId = $order->get_id();
        $order->calculate_totals();
        $this->updateOrderPostMeta($orderId, $order);
        $result = $this->processOrderPayment($orderId);

        if (isset($result['result'])
            && 'success' === $result['result']
        ) {
            $order->payment_complete();
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

        $order = $this->addShippingMethodsToOrder(
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
            wp_send_json_success($result);
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
            wp_send_json_error($message);
        }
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
     * Add shipping methods to order
     *
     * @param array $shippingMethod
     * @param array $shippingAddress
     * @param       $order
     *
     * @return mixed
     */
    protected function addShippingMethodsToOrder(
        $order
    ) {
        $item = new WC_Order_Item_Shipping();
        $shippingMethod = new Mollie_WC_PayPalButton_CustomShippingMethod();
        $shippingMethod->calculate_shipping();
        //OJO QUE ESTO LO TENGO QUE QUITAR!!!

        WC()->initialize_session();
        $shippingMethodId = $shippingMethod->id;
        WC()->session->set(
            'chosen_shipping_methods',
            array($shippingMethodId)
        );
        $item->set_props(
            array(
                'method_title' => 'PayPalButton Fixed Shipping',
                'total' => wc_format_decimal(
                    $shippingMethod->rates['PayPalButtonFixedShipping']->get_cost(
                    )
                ),
            )
        );

        $order->add_item($item);

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
