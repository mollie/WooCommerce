<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\PayPalButton;

use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;
use WC_Data_Exception;

class PayPalAjaxRequests
{
    /**
     * @var
     */
    protected $gateway;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * PayPalAjaxRequests constructor.
     *
     * @param  $gateway
     */
    public function __construct($gateway, NoticeInterface $notice, Logger $logger)
    {
        $this->gateway = $gateway;
        $this->notice = $notice;
        $this->logger = $logger;
    }

    /**
     * Adds all the Ajax actions to perform the whole workflow
     */
    public function bootstrapAjaxRequest()
    {
        add_action(
            'wp_ajax_' . PropertiesDictionary::CREATE_ORDER,
            [$this, 'createWcOrder']
        );
        add_action(
            'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER,
            [$this, 'createWcOrder']
        );
        add_action(
            'wp_ajax_' . PropertiesDictionary::CREATE_ORDER_CART,
            [$this, 'createWcOrderFromCart']
        );
        add_action(
            'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER_CART,
            [$this, 'createWcOrderFromCart']
        );
        add_action(
            'wp_ajax_' . PropertiesDictionary::UPDATE_AMOUNT,
            [$this, 'updateAmount']
        );
        add_action(
            'wp_ajax_nopriv_' . PropertiesDictionary::UPDATE_AMOUNT,
            [$this, 'updateAmount']
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
        if (!$this->isNonceValid()) {
            return;
        }
        $payPalRequestDataObject->orderData('productDetail');

        $order = wc_create_order();
        $order->add_product(
            wc_get_product($payPalRequestDataObject->productId()),
            $payPalRequestDataObject->productQuantity()
        );

        $surcharge = new Surcharge();
        $surchargeHandler = new GatewaySurchargeHandler($surcharge);
        $order = $surchargeHandler->addSurchargeFeeProductPage($order, 'mollie_wc_gateway_paypal');

        $orderId = $order->get_id();
        $order->calculate_totals();
        $this->updateOrderPostMeta($orderId, $order);

        $result = $this->processOrderPayment($orderId);

        if (
            isset($result['result'])
            && 'success' === $result['result']
        ) {
            wp_send_json_success($result);
        } else {
            $message = sprintf(
            /* translators: Placeholder 1: Payment method title */
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'PayPal'
            );

            $this->logger->debug($message, ['error']);
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
        if (!$this->isNonceValid()) {
            return;
        }
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData('cart');
        list($cart, $order) = $this->createOrderFromCart();
        $orderId = $order->get_id();
        $order->calculate_totals();
        $surcharge = new Surcharge();
        $surchargeHandler = new GatewaySurchargeHandler($surcharge);
        $order = $surchargeHandler->addSurchargeFeeProductPage($order, 'mollie_wc_gateway_paypal');
        $this->updateOrderPostMeta($orderId, $order);
        $result = $this->processOrderPayment($orderId);
        if (
            isset($result['result'])
            && 'success' === $result['result']
        ) {
            wp_send_json_success($result);
        } else {
            $message = sprintf(
            /* translators: Placeholder 1: Payment method title */
                __(
                    'Could not create %s payment.',
                    'mollie-payments-for-woocommerce'
                ),
                'PayPal'
            );

            $this->notice->addNotice($message, 'error');
            wp_send_json_error($message);
        }
    }

    public function updateAmount()
    {
        if (!$this->isNonceValid()) {
            wp_send_json_error('no nonce');
        }
        $payPalRequestDataObject = $this->payPalDataObjectHttp();
        $payPalRequestDataObject->orderData('productDetail');
        $order = new WCOrderCalculator();
        $order->set_currency(get_woocommerce_currency());
        $order->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax'));
        $order->add_product(
            wc_get_product($payPalRequestDataObject->productId()),
            $payPalRequestDataObject->productQuantity()
        );

        $updatedAmount = $order->calculate_totals();

        wp_send_json_success($updatedAmount);
    }

    /**
     * Data Object to collect and validate all needed data collected
     * through HTTP
     *
     * @return PayPalDataObjectHttp
     */
    protected function PayPalDataObjectHttp(): PayPalDataObjectHttp
    {
        return new PayPalDataObjectHttp($this->logger);
    }

    /**
     * Update order post meta
     *
     * @param string $orderId
     * @param        $order
     */
    protected function updateOrderPostMeta($orderId, $order)
    {
        $order->update_meta_data('_customer_user', get_current_user_id());
        $order->update_meta_data('_payment_method', 'mollie_wc_gateway_paypal');
        $order->update_meta_data('_payment_method_title', 'PayPal');
        $order->update_meta_data('_mollie_payment_method_button', 'PayPalButton');
        //this saves the order
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
        return $this->gateway->process_payment($orderId);
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
        return [$cart, $order];
    }

    /**
     * Checks if the nonce in the data object is valid
     *
     * @param PayPalDataObjectHttp $PayPalRequestDataObject
     */
    protected function isNonceValid(): bool
    {
        $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$nonce) {
            return false;
        }
        $verifyNonce = wp_verify_nonce(
            $nonce,
            'mollie_PayPal_button'
        );
        return $verifyNonce == 1 || $verifyNonce == 2;
    }
}
