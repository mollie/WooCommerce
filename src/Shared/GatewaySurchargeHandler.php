<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Shared;

use Mollie\WooCommerce\Gateway\Surcharge;
use WC_Order;
use WC_Order_Item_Fee;
class GatewaySurchargeHandler
{
    protected $gatewayFeeLabel;
    protected $surcharge;
    /**
     * The payment method posted to the Store API checkout in this request
     */
    protected ?string $storeApiPaymentMethod = null;
    /**
     * The order paid through the Store API order-pay route in this request
     */
    protected int $storeApiOrderPayId = 0;
    /**
     * GatewaySurchargeHandler constructor.
     */
    public function __construct(Surcharge $surcharge)
    {
        $this->surcharge = $surcharge;
        add_action('init', [$this, 'initializeGatewayFeeLabel']);
        add_action('init', [$this, 'surchargeActions']);
    }
    public function initializeGatewayFeeLabel()
    {
        $this->gatewayFeeLabel = $this->surchargeFeeOption();
    }
    public function surchargeActions()
    {
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_engraving_fees']);
        add_action('wp_enqueue_scripts', function () {
            $this->enqueueSurchargeScript();
        });
        add_action('wp_ajax_update_surcharge_order_pay', function () {
            $this->updateSurchargeOrderPay();
        });
        add_action('wp_ajax_nopriv_update_surcharge_order_pay', function () {
            $this->updateSurchargeOrderPay();
        });
        add_action('woocommerce_order_item_meta_end', [$this, 'renderHiddenOrderKeyFields'], 10, 4);
        add_action('woocommerce_before_pay_action', [$this, 'recalculateSurchargeOnPayAction']);
        add_filter('rest_request_before_callbacks', [$this, 'rememberStoreApiCheckout'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'recalculateSurchargeOnStoreApiOrderPay']);
    }
    public function renderHiddenOrderKeyFields($item_id, $item, $order, $bool = \false)
    {
        if (!is_callable([$order, 'get_order_key'])) {
            return;
        }
        $orderKey = $order->get_order_key();
        $nonce = wp_create_nonce('mollie_surcharge_' . $orderKey);
        ?>
        <input type="hidden" name="mollie-woocommerce-orderKey" value="<?php 
        echo esc_attr($orderKey);
        ?>">
        <input type="hidden" name="mollie-surcharge-nonce" value="<?php 
        echo esc_attr($nonce);
        ?>">
        <?php 
    }
    public function enqueueSurchargeScript(): void
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        wp_enqueue_script('gatewaySurcharge');
        wp_localize_script('gatewaySurcharge', 'surchargeData', ['ajaxUrl' => admin_url('admin-ajax.php'), 'gatewayFeeLabel' => $this->gatewayFeeLabel]);
    }
    public function addSurchargeFeeProductPage($order, $gateway)
    {
        $gatewaySettings = $this->gatewaySettings($gateway);
        if (!isset($gatewaySettings['payment_surcharge']) || $gatewaySettings['payment_surcharge'] === Surcharge::NO_FEE) {
            return $order;
        }
        $order->calculate_totals();
        $orderAmount = $order->get_total();
        if ($this->surcharge->aboveMaxLimit(floatval($orderAmount), $gatewaySettings)) {
            return $order;
        }
        $amount = $this->surcharge->calculateFeeAmountOrder($order, $gatewaySettings);
        if ($amount > 0) {
            $this->orderRemoveFee($order);
            $this->orderAddFee($order, $amount, $this->gatewayFeeLabel);
            $order->calculate_totals();
        }
        return $order;
    }
    public function updateSurchargeOrderPay()
    {
        $order = $this->verifyNonce() ? $this->canProcessOrder() : \false;
        $gatewayName = $order ? $this->canProcessGateway() : \false;
        if (!$order || !$gatewayName) {
            // wp_send_json_error() ends the request
            wp_send_json_error();
        }
        $amount = $this->applyOrderSurcharge($order, $gatewayName);
        $data = ['amount' => $amount > 0 ? $amount : \false, 'currency' => get_woocommerce_currency_symbol(), 'newTotal' => $order->get_total()];
        if ($amount > 0) {
            $data['name'] = $this->gatewayFeeLabel;
        }
        wp_send_json_success($data);
    }
    /**
     * Applies the surcharge of the gateway submitted on the order-pay form before
     * the payment is processed.
     * WC_Form_Handler::pay_action() verifies the nonce, the order key and
     * that the order needs payment before firing this hook.
     *
     * @param mixed $order
     */
    public function recalculateSurchargeOnPayAction($order): void
    {
        if (!$order instanceof WC_Order || !$order->needs_payment()) {
            return;
        }
        $gatewayName = $this->canProcessGateway('payment_method');
        if (!$gatewayName) {
            return;
        }
        $this->recalculateOrderSurcharge($order, $gatewayName);
    }
    /**
     * Remembers the payment method posted to the Store API checkout, and the order
     * paid through the Store API order-pay route, so the surcharge follows the
     * payment method the order is placed with.
     *
     * @param mixed $response
     * @param mixed $handler
     * @param mixed $request
     * @return mixed The unchanged response
     */
    public function rememberStoreApiCheckout($response, $handler, $request)
    {
        if (!$request instanceof \WP_REST_Request || $request->get_method() !== 'POST') {
            return $response;
        }
        if (!preg_match('#^/wc/store(?:/v\d+)?/checkout(?:/(\d+))?$#', $request->get_route(), $matches)) {
            return $response;
        }
        $paymentMethod = $request->get_param('payment_method');
        $this->storeApiPaymentMethod = is_string($paymentMethod) ? sanitize_text_field($paymentMethod) : '';
        $this->storeApiOrderPayId = isset($matches[1]) ? absint($matches[1]) : 0;
        return $response;
    }
    /**
     * Applies the surcharge of the order's payment method when an existing order is
     * paid through the Store API order-pay route (POST /wc/store/v1/checkout/{id}),
     * as recalculateSurchargeOnPayAction() does for the order-pay form. The route has
     * already checked that the order needs payment and set the payment method on it.
     *
     * @param mixed $order
     */
    public function recalculateSurchargeOnStoreApiOrderPay($order): void
    {
        if (!$order instanceof WC_Order || $this->storeApiOrderPayId !== $order->get_id()) {
            return;
        }
        $gatewayName = $order->get_payment_method();
        if (!$this->isEnabledGateway($gatewayName)) {
            return;
        }
        $this->recalculateOrderSurcharge($order, $gatewayName);
    }
    /**
     * Replaces the surcharge of an order awaiting payment with the surcharge of the
     * gateway it is about to be paid with. The gateway must have been validated before.
     */
    protected function recalculateOrderSurcharge(WC_Order $order, string $gatewayName): void
    {
        if (!$order->needs_payment()) {
            return;
        }
        if (!$this->orderHasSurchargeFee($order) && !$this->gatewayHasSurcharge($gatewayName)) {
            return;
        }
        $this->applyOrderSurcharge($order, $gatewayName);
    }
    /**
     * Replaces any surcharge on the order with the surcharge of the given gateway.
     * The gateway must have been validated by canProcessGateway() before.
     *
     * @return float The surcharge amount added, 0 if none applies
     */
    protected function applyOrderSurcharge(WC_Order $order, string $gatewayName): float
    {
        $this->orderRemoveFee($order);
        if (!$this->gatewayHasSurcharge($gatewayName)) {
            return 0.0;
        }
        $gatewaySettings = $this->gatewaySettings($gatewayName);
        if ($this->surcharge->aboveMaxLimit((float) $order->get_total(), $gatewaySettings)) {
            return 0.0;
        }
        $amount = (float) $this->surcharge->calculateFeeAmountOrder($order, $gatewaySettings);
        if ($amount <= 0) {
            return 0.0;
        }
        $this->orderAddFee($order, $amount, $this->gatewayFeeLabel);
        $order->calculate_totals();
        return $amount;
    }
    protected function gatewayHasSurcharge(string $gatewayName): bool
    {
        if (!$this->isMollieGateway($gatewayName)) {
            return \false;
        }
        $gatewaySettings = $this->gatewaySettings($gatewayName);
        return is_array($gatewaySettings) && isset($gatewaySettings['payment_surcharge']) && $gatewaySettings['payment_surcharge'] !== Surcharge::NO_FEE;
    }
    protected function orderHasSurchargeFee(WC_Order $order): bool
    {
        foreach ($order->get_fees() as $fee) {
            if ($this->isSurchargeFee($fee->get_name())) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * An empty label would match every fee on the order
     */
    protected function isSurchargeFee($feeName): bool
    {
        $label = (string) $this->gatewayFeeLabel;
        return $label !== '' && strpos((string) $feeName, $label) !== \false;
    }
    public function add_engraving_fees()
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        $cart = WC()->cart;
        $gateway = $this->chosenGateway();
        if (!$gateway) {
            return;
        }
        $gatewaySettings = $this->gatewaySettings($gateway);
        if (!$gatewaySettings) {
            return;
        }
        if (!isset($gatewaySettings['payment_surcharge']) || $gatewaySettings['payment_surcharge'] === Surcharge::NO_FEE) {
            return;
        }
        $isRecurringCart = !empty($cart->recurring_cart_key);
        if ($isRecurringCart) {
            return;
        }
        $cartAmount = $cart->get_subtotal() + $cart->get_subtotal_tax();
        if ($this->surcharge->aboveMaxLimit($cartAmount, $gatewaySettings)) {
            return;
        }
        $amount = $this->surcharge->calculateFeeAmount($cart, $gatewaySettings);
        $cart->add_fee($this->gatewayFeeLabel, $amount, \true, 'standard');
    }
    /**
     * Verify nonce for surcharge operations
     */
    protected function verifyNonce(): bool
    {
        $orderKey = sanitize_text_field(wc_get_post_data_by_key('orderKey', ''));
        $nonce = wc_get_post_data_by_key('nonce', '');
        if (!$orderKey || !$nonce) {
            return \false;
        }
        return (bool) wp_verify_nonce($nonce, 'mollie_surcharge_' . $orderKey);
    }
    protected function chosenGateway()
    {
        $gateway = $this->storeApiPaymentMethod ?? WC()->session->get('chosen_payment_method');
        if (empty($gateway)) {
            $gateway = empty($_REQUEST['payment_method']) ? '' : sanitize_text_field(wp_unslash($_REQUEST['payment_method']));
        }
        if (!$this->isMollieGateway($gateway)) {
            return \false;
        }
        return $gateway;
    }
    protected function isMollieGateway($gateway): bool
    {
        return !empty($gateway) && strpos($gateway, 'mollie_wc_gateway_') === 0;
    }
    private function gatewaySettings($gateway)
    {
        $optionName = sprintf('%s_settings', $gateway);
        $allSettings = get_option($optionName, \false);
        if (!$allSettings) {
            return \false;
        }
        return $allSettings;
    }
    /**
     * @throws \Exception
     * @param wc_order $order
     */
    protected function orderRemoveFee($order)
    {
        $fees = $order->get_fees();
        foreach ($fees as $fee) {
            $feeName = $fee->get_name();
            $feeId = $fee->get_id();
            if ($this->isSurchargeFee($feeName)) {
                $order->remove_item($feeId);
                wc_delete_order_item($feeId);
                $order->calculate_totals();
            }
        }
    }
    protected function orderAddFee($order, $amount, $surchargeName)
    {
        $item_fee = new WC_Order_Item_Fee();
        $item_fee->set_name($surchargeName);
        $item_fee->set_amount($amount);
        $item_fee->set_total($amount);
        $item_fee->set_tax_status('taxable');
        $order->add_item($item_fee);
        $order->calculate_totals();
    }
    /**
     * Get and validate order with order key verification
     */
    protected function canProcessOrder()
    {
        $orderKey = sanitize_text_field(wc_get_post_data_by_key('orderKey', ''));
        if (!$orderKey) {
            return \false;
        }
        $orderId = wc_get_order_id_by_order_key($orderKey);
        if (!$orderId) {
            return \false;
        }
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return \false;
        }
        // Only orders still awaiting payment may have their surcharge changed
        if (!$order->needs_payment()) {
            return \false;
        }
        return $order;
    }
    /**
     * Returns the posted gateway ID if it belongs to a registered, enabled gateway
     *
     * @return string|false
     */
    protected function canProcessGateway(string $postKey = 'method')
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        if (!isset($_POST[$postKey])) {
            return \false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $postedMethod = wc_clean(wp_unslash($_POST[$postKey]));
        return $this->isEnabledGateway($postedMethod) ? $postedMethod : \false;
    }
    /**
     * @param mixed $gatewayId
     */
    protected function isEnabledGateway($gatewayId): bool
    {
        if (!is_string($gatewayId) || $gatewayId === '') {
            return \false;
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways[$gatewayId] ?? null;
        return $gateway instanceof \WC_Payment_Gateway && $gateway->enabled === 'yes';
    }
    protected function surchargeFeeOption()
    {
        $rawLabel = get_option('mollie-payments-for-woocommerce_gatewayFeeLabel', $this->surcharge->defaultFeeLabel());
        if (function_exists('icl_register_string')) {
            icl_register_string('mollie-payments-for-woocommerce', 'gatewayFeeLabel', $rawLabel);
        }
        return apply_filters('wpml_translate_single_string', $rawLabel, 'mollie-payments-for-woocommerce', 'gatewayFeeLabel');
    }
}
