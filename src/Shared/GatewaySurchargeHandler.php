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
     * GatewaySurchargeHandler constructor.
     */
    public function __construct(Surcharge $surcharge)
    {
        $this->surcharge = $surcharge;
        add_action('after_setup_theme', [$this, 'initializeGatewayFeeLabel']);
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
        add_action('woocommerce_order_item_meta_end', [$this, 'setHiddenOrderId'], 10, 4);
    }
    public function setHiddenOrderId($item_id, $item, $order, $bool = \false)
    {
        $nonce = wp_create_nonce('mollie_surcharge_' . $order->get_id());
        ?>
        <input type="hidden" name="mollie-woocommerce-orderId" value="<?php 
        echo esc_attr($order->get_id());
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
        if (!$this->verifyNonce()) {
            return;
        }
        $order = $this->canProcessOrder();
        if (!$order) {
            return;
        }
        $gatewayName = $this->canProcessGateway();
        if (!$gatewayName) {
            return;
        }
        $this->orderRemoveFee($order);
        $gatewaySettings = $this->gatewaySettings($gatewayName);
        $orderAmount = (float) $order->get_total();
        if ($this->surcharge->aboveMaxLimit($orderAmount, $gatewaySettings)) {
            return;
        }
        if (!isset($gatewaySettings['payment_surcharge']) || $gatewaySettings['payment_surcharge'] === Surcharge::NO_FEE) {
            $data = ['amount' => \false, 'currency' => get_woocommerce_currency_symbol(), 'newTotal' => $order->get_total()];
            wp_send_json_success($data);
            return;
        }
        $amount = $this->surcharge->calculateFeeAmountOrder($order, $gatewaySettings);
        if ($amount > 0) {
            $this->orderAddFee($order, $amount, $this->gatewayFeeLabel);
            $order->calculate_totals();
            $newTotal = $order->get_total();
            $data = ['amount' => $amount, 'name' => $this->gatewayFeeLabel, 'currency' => get_woocommerce_currency_symbol(), 'newTotal' => $newTotal];
            wp_send_json_success($data);
        }
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
        $orderId = (int) wc_get_post_data_by_key('orderId', '');
        $nonce = wc_get_post_data_by_key('nonce', '');
        if (!$orderId || !$nonce) {
            return \false;
        }
        return (bool) wp_verify_nonce($nonce, 'mollie_surcharge_' . $orderId);
    }
    protected function chosenGateway()
    {
        $gateway = WC()->session->get('chosen_payment_method');
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
        return !empty($gateway) && strpos($gateway, 'mollie_wc_gateway_') !== \false;
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
            if (strpos($feeName, $this->gatewayFeeLabel) !== \false) {
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
        $postedOrderId = (int) wc_get_post_data_by_key('orderId', '');
        if (!$postedOrderId) {
            return \false;
        }
        $order = wc_get_order($postedOrderId);
        if (!$order) {
            return \false;
        }
        return $order;
    }
    protected function canProcessGateway()
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        if (!isset($_POST['method'])) {
            return \false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $postedMethod = wc_clean(wp_unslash($_POST['method']));
        $gateway = !empty($postedMethod) ? $postedMethod : \false;
        if (!$gateway) {
            return \false;
        }
        if (!$this->isMollieGateway($gateway)) {
            return \false;
        }
        return $gateway;
    }
    protected function surchargeFeeOption()
    {
        return get_option('mollie-payments-for-woocommerce_gatewayFeeLabel', $this->surcharge->defaultFeeLabel());
    }
}
