<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use InvalidArgumentException;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Log\LoggerInterface as Logger;
use UnexpectedValueException;
use WC_Order;

class MolliePaymentGatewayHandler
{
    /**
     * @var bool
     */
    protected static $alreadyDisplayedAdminInstructions = false;
    protected static $alreadyDisplayedCustomerInstructions = false;
    /**
     * Recurring total, zero does not define a recurring total
     *
     * @var array
     */
    protected $recurring_totals = [];
    /**
     * @var PaymentMethodI
     */
    public $paymentMethod;
    /**
     * @var string
     */
    protected $default_title;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * @var PaymentProcessor
     */
    protected $paymentProcessor;
    /**
     * @var MollieOrderService
     */
    protected $mollieOrderService;
    /**
     * @var HttpResponse
     */
    protected $httpResponse;
    /**
     * @var OrderInstructionsManager
     */
    protected $orderInstructionsManager;
    /**
     * @var Data
     */
    protected $dataService;
    /**
     * @var MollieObject
     */
    protected $mollieObject;
    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;
    /**
     * @var string
     */
    protected $pluginId;

    public string $enabled;
    public string $id;

    /**
     *
     */
    public function __construct(
        PaymentMethodI $paymentMethod,
        PaymentProcessor $paymentProcessor,
        OrderInstructionsManager $orderInstructionsProcessor,
        MollieOrderService $mollieOrderService,
        Data $dataService,
        Logger $logger,
        NoticeInterface $notice,
        HttpResponse $httpResponse,
        MollieObject $mollieObject,
        PaymentFactory $paymentFactory,
        string $pluginId
    ) {

        $this->paymentMethod = $paymentMethod;
        $this->logger = $logger;
        $this->notice = $notice;
        $this->paymentProcessor = $paymentProcessor;
        $this->orderInstructionsManager = $orderInstructionsProcessor;
        $this->mollieOrderService = $mollieOrderService;
        $this->httpResponse = $httpResponse;
        $this->dataService = $dataService;
        $this->mollieObject = $mollieObject;
        $this->paymentFactory = $paymentFactory;
        $this->pluginId = $pluginId;

        // Use gateway class name as gateway id
        $this->gatewayId();

        $this->mollieOrderService->setGateway($this);

        add_action(
            'woocommerce_api_' . $this->id,
            [$this->mollieOrderService, 'onWebhookAction']
        );

        // Adjust title and text on Order Received page in some cases, see issue #166
        add_filter('the_title', [$this, 'onOrderReceivedTitle'], 10, 2);
        add_filter(
            'woocommerce_thankyou_order_received_text',
            [$this, 'onOrderReceivedText'],
            10,
            2
        );

        $isEnabledAtWoo = $this->paymentMethod->getProperty('enabled') ?
            $this->paymentMethod->getProperty('enabled') :
            'yes';
        $this->enabled = $isEnabledAtWoo;

        if ($this->enabled === 'yes' && $this->paymentMethod->getProperty('filtersOnBuild')) {
            $this->paymentMethod->filtersOnBuild();
        }
        //$this->refundProcessor = new RefundProcessor($this);
    }

    public function paymentMethod(): PaymentMethodI
    {
        return $this->paymentMethod;
    }

    public function paymentService()
    {
        return $this->paymentProcessor;
    }

    public function dataService()
    {
        return $this->dataService;
    }

    public function pluginId()
    {
        return $this->pluginId;
    }

    protected function gatewayId()
    {
        $paymentMethodId = $this->paymentMethod->getProperty('id');
        $this->id = 'mollie_wc_gateway_' . $paymentMethodId;
        return $this->id;
    }

    /**
     * Check if the gateway is available for use
     *
     * @return bool
     */
    public function is_available($gateway): bool
    {
        if (!$this->checkEnabledNorDirectDebit($gateway)) {
            return false;
        }
        if (!$this->cartAmountAvailable()) {
            return true;
        }

        $order_total = WC()->cart ? WC()->cart->get_total('edit') : 0;
        $currency = $this->getCurrencyFromOrder();
        $billingCountry = $this->getBillingCountry();
        $paymentLocale = $this->dataService->getPaymentLocale();

        try {
            $filters = $this->dataService->getFilters(
                $currency,
                $order_total,
                $paymentLocale,
                $billingCountry
            );
        } catch (InvalidArgumentException $exception) {
            $this->logger->debug(
                $exception->getMessage()
            );
            return false;
        }

        $status = $this->isAvailableMethodInCheckout($filters);

        return $this->isAllowedBillingCountry($billingCountry, $status);
    }

    /**
     * Check if payment method is available in checkout based on amount, currency and sequenceType
     *
     * @param $filters
     *
     * @return bool
     */
    public function isAvailableMethodInCheckout($filters): bool
    {
        $useCache = true;
        $methods = $this->dataService->getApiPaymentMethods(
            $useCache,
            $filters
        );

        // Get the ID of the WooCommerce/Mollie payment method
        $woocommerce_method = $this->paymentMethod->getProperty('id');

        // Set all other payment methods to false, so they can be updated if available
        foreach ($methods as $method) {
            if ($method['id'] === $woocommerce_method) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|false|int
     */
    public function get_recurring_total()
    {
        if (isset(WC()->cart)) {
            if (!empty(WC()->cart->recurring_carts)) {
                $this->recurring_totals = []; // Reset for cached carts

                foreach (WC()->cart->recurring_carts as $cart) {
                    if (!$cart->prices_include_tax) {
                        $this->recurring_totals[] = $cart->cart_contents_total;
                    } else {
                        $this->recurring_totals[] = $cart->cart_contents_total
                            + $cart->tax_total;
                    }
                }
            } else {
                return false;
            }
        }

        return $this->recurring_totals;
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

        $this->logger->debug(
            __METHOD__ . ' - ' . $this->id
            . ": Order $order_id does not need a payment by Mollie (payment {$payment->id}).",
            [true]
        );
    }

    /**
     * @param WC_Order $order
     *
     * @return string
     */
    public function getReturnRedirectUrlForOrder(WC_Order $order): string
    {
        $order_id = $order->get_id();
        $debugLine = __METHOD__
            . " {$order_id}: Determine what the redirect URL in WooCommerce should be.";
        $this->logger->debug($debugLine);
        $hookReturnPaymentStatus = 'success';
        $gateway = wc_get_payment_gateway_by_order($order);
        $returnRedirect = $gateway->get_return_url($order);
        $failedRedirect = $order->get_checkout_payment_url(false);

        $this->mollieOrderService->setGateway($this);
        if ($this->mollieOrderService->orderNeedsPayment($order)) {
            $hasCancelledMolliePayment = $this->paymentObject()
                ->getCancelledMolliePaymentId($order_id);

            if ($hasCancelledMolliePayment) {
                $order_status_cancelled_payments = $this->paymentMethod->getOrderStatusCancelledPayments();

                // If user set all cancelled payments to also cancel the order,
                // redirect to /checkout/order-received/ with a message about the
                // order being cancelled. Otherwise redirect to /checkout/order-pay/ so
                // customers can try to pay with another payment method.
                if ($order_status_cancelled_payments === 'cancelled') {
                    return $returnRedirect;
                } else {
                    $this->notice->addNotice(
                        'error',
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
                if (
                    !$payment->isOpen()
                    && !$payment->isPending()
                    && !$payment->isPaid()
                    && !$payment->isAuthorized()
                ) {
                    $this->notice->addNotice(
                        'error',
                        __(
                            'Your payment was not successful. Please complete your order with a different payment method.',
                            'mollie-payments-for-woocommerce'
                        )
                    );
                    // Return to order payment page
                    return $failedRedirect;
                }
                if ($payment->method === "giftcard") {
                    $this->paymentMethod->debugGiftcardDetails($payment, $order);
                }
            } catch (UnexpectedValueException $exc) {
                $this->notice->addNotice(
                    'error',
                    __(
                        'Your payment was not successful. Please complete your order with a different payment method.',
                        'mollie-payments-for-woocommerce'
                    )
                );
                $exceptionMessage = $exc->getMessage();
                $debugLine = __METHOD__
                    . " Problem processing the payment. {$exceptionMessage}";
                $this->logger->debug($debugLine);
                $hookReturnPaymentStatus = 'failed';
            }
        }
        do_action(
            $this->pluginId . '_customer_return_payment_'
            . $hookReturnPaymentStatus,
            $order
        );

        /*
         * Return to order received page
         */
        return $returnRedirect;
    }

    /**
     * Retrieve the payment object
     *
     * @return MollieObject
     */
    public function paymentObject(): MollieObject
    {
        return $this->mollieObject;
    }

    /**
     * Retrieve the active payment object
     *
     * @param $orderId
     * @param $useCache
     *
     * @return Payment
     * @throws UnexpectedValueException
     */
    public function activePaymentObject($orderId, $useCache): Payment
    {
        $paymentObject = $this->paymentObject();
        $activePaymentObject = $paymentObject->getActiveMolliePayment(
            $orderId,
            $useCache
        );

        if ($activePaymentObject === null) {
            throw new UnexpectedValueException(
                esc_html(sprintf("Active Payment Object is not a valid Payment Resource instance. Order ID: %s", $orderId))
            );
        }

        return $activePaymentObject;
    }

    /**
     * @param      $title
     * @param null $id
     *
     * @return string|void
     */
    public function onOrderReceivedTitle($title, $id = null)
    {
        if (is_order_received_page() && get_the_ID() === $id) {
            $order = false;
            $orderReceived = get_query_var('order-received');
            $order_id = apply_filters(
                'woocommerce_thankyou_order_id',
                absint($orderReceived)
            );
            $order_key = apply_filters(
                'woocommerce_thankyou_order_key',
                empty($_GET['key']) ? '' : wc_clean(filter_input(INPUT_GET, 'key', FILTER_SANITIZE_SPECIAL_CHARS))// WPCS: input var ok, CSRF ok.
            );
            if ($order_id > 0) {
                $order = wc_get_order($order_id);

                if (!is_a($order, 'WC_Order')) {
                    return $title;
                }

                $order_key_db = $order->get_order_key();

                if ($order_key_db !== $order_key) {
                    $order = false;
                }
            }

            if ($order === false) {
                return $title;
            }

            $order_payment_method = $order->get_payment_method();

            // Invalid gateway
            if ($this->id !== $order_payment_method) {
                return $title;
            }

            // Title for cancelled orders
            if ($order->has_status('cancelled')) {
                return __(
                    'Order cancelled',
                    'mollie-payments-for-woocommerce'
                );
            }

            // Checks and title for pending/open orders
            $payment = $this->paymentObject()->getActiveMolliePayment(
                $order->get_id()
            );

            // Mollie payment not found or invalid gateway
            if (!$payment || $payment->method !== $this->paymentMethod->getProperty('id')) {
                return $title;
            }

            if ($payment->isOpen()) {
                // Add a message to log and order explaining a payment with status "open", only if it hasn't been added already
                if ($order->get_meta('_mollie_open_status_note') !== '1') {
                    // Get payment method title
                    $payment_method_title = $this->method_title;

                    // Add message to log
                    $this->logger->debug(
                        $this->id
                        . ': Customer returned to store, but payment still pending for order #'
                        . $order_id
                        . '. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.'
                    );

                    // Add message to order as order note
                    $order->add_order_note(
                        sprintf(
                        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                            __(
                                '%1$s payment still pending (%2$s) but customer already returned to the store. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.',
                                'mollie-payments-for-woocommerce'
                            ),
                            $payment_method_title,
                            $payment->id . ($payment->mode === 'test' ? (' - '
                                . __(
                                    'test mode',
                                    'mollie-payments-for-woocommerce'
                                )) : '')
                        )
                    );
                    $order->update_meta_data('_mollie_open_status_note', '1');
                    $order->save();
                }

                // Update the title on the Order received page to better communicate that the payment is pending.
                $title .= __(
                    ', payment pending.',
                    'mollie-payments-for-woocommerce'
                );

                return $title;
            }
        }

        return $title;
    }

    /**
     * @param          $text
     * @param WC_Order| null $order
     *
     * @return string|void
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
            return __(
                'Your order has been cancelled.',
                'mollie-payments-for-woocommerce'
            );
        }

        return $text;
    }

    /**
     * Get the transaction URL.
     *
     * @param WC_Order $order
     *
     * @return string
     */
    /*public function get_transaction_url($order): string
    {
        $isPaymentApi = substr($order->get_meta('_mollie_order_id', true), 0, 3) === 'tr_'  ;
        $resource = ($order->get_meta('_mollie_order_id', true) && !$isPaymentApi) ? 'orders' : 'payments';

        $this->view_transaction_url = 'https://my.mollie.com/dashboard/'
            . $resource . '/%s?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner';

        return parent::get_transaction_url($order);
    }*/

    /**
     * Get the correct currency for this payment or order
     * On order-pay page, order is already created and has an order currency
     * On checkout, order is not created, use get_woocommerce_currency
     *
     * @return string
     */
    public function getCurrencyFromOrder()
    {
        global $wp;
        if (!empty($wp->query_vars['order-pay'])) {
            $order_id = $wp->query_vars['order-pay'];
            $order = wc_get_order($order_id);

            $currency = $this->dataService->getOrderCurrency($order);
        } else {
            $currency = get_woocommerce_currency();
        }
        return $currency;
    }

    /**
     * Retrieve the customer's billing country
     * or fallback to the shop country
     *
     * @return mixed|void|null
     */
    public function getBillingCountry()
    {
        $customerExistsAndHasCountry = WC()->customer && !empty(WC()->customer->get_billing_country());
        $fallbackToShopCountry = wc_get_base_location()['country'];
        $billingCountry = $customerExistsAndHasCountry ? WC()->customer->get_billing_country() : $fallbackToShopCountry;

        return apply_filters(
            $this->pluginId
            . '_is_available_billing_country_for_payment_gateways',
            $billingCountry
        );
    }

    /**
     * Check the 'allowed_countries' setting
     * and return false if $billingCountry is in the list of not allowed.
     *
     * @param string $billingCountry
     * @param bool $status
     * @return bool
     */
    protected function isAllowedBillingCountry($billingCountry, $status)
    {
        $allowedCountries = $this->paymentMethod->getProperty('allowed_countries');
        //if no country is selected then this does not apply
        $bCountryIsAllowed = empty($allowedCountries)
            || in_array(
                $billingCountry,
                $allowedCountries,
                true
            );
        if (!$bCountryIsAllowed) {
            $status = false;
        }
        return $status;
    }

    /**
     * In WooCommerce check if the gateway is available for use (WooCommerce settings)
     * but also check if is not direct debit as this should not be shown in checkout
     *
     * @return bool
     */
    protected function checkEnabledNorDirectDebit($gateway): bool
    {
        if ($gateway->enabled !== 'yes') {
            return false;
        }
        if ($gateway->id === SharedDataDictionary::DIRECTDEBIT) {
            return false;
        }
        return true;
    }

    /**
     * Check if the cart amount is available and > 0
     *
     * @return bool
     */
    protected function cartAmountAvailable()
    {
        return WC()->cart && WC()->cart->get_total('edit') > 0;
    }

    /**
     * TODO still used by the refund processor
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * TODO still used by the refund processor
     * @return PaymentFactory
     */
    public function getPaymentFactory(): PaymentFactory
    {
        return $this->paymentFactory;
    }
}
