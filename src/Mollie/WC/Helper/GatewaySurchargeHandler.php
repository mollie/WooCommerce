<?php

class Mollie_WC_Helper_GatewaySurchargeHandler
{
    const NO_FEE = 'no_fee';
    const FIXED_FEE = 'fixed_fee';
    const PERCENTAGE = 'percentage';
    const FIXED_AND_PERCENTAGE = 'fixed_fee_percentage';
    const DEFAULT_FEE_LABEL = 'Gateway Fee';
    public $gatewayFeeLabel;

    /**
     * Mollie_WC_Helper_ApplePayDirectHandler constructor.
     */
    public function __construct()
    {
        $this->gatewayFeeLabel = get_option('mollie-payments-for-woocommerce_gatewayFeeLabel', __(self::DEFAULT_FEE_LABEL, 'mollie-payments-for-woocommerce'));

        add_filter( 'woocommerce_cart_calculate_fees', [$this, 'add_engraving_fees'], 10, 1 );
        add_action( 'wp_enqueue_scripts', [$this, 'enqueueSurchargeScript' ]);
        add_action(
            'wp_ajax_' . 'update_surcharge_order_pay',
            array($this, 'updateSurchargeOrderPay')
        );
        add_action(
            'wp_ajax_nopriv_' . 'update_surcharge_order_pay',
            array($this, 'updateSurchargeOrderPay')
        );
        add_action( 'woocommerce_order_item_meta_end',[$this, 'setHiddenOrderId'], 10, 4);
    }

    public function setHiddenOrderId($item_id, $item, $order, $bool = false)
    {
        ?>
        <input type="hidden" name="mollie-woocommerce-orderId" value="<?php echo $order->get_id() ?>">
        <?php
    }

    public function enqueueSurchargeScript()
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        wp_enqueue_script('gatewaySurcharge');
        wp_localize_script(
            'gatewaySurcharge',
            'surchargeData',
            [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'gatewayFeeLabel' => $this->gatewayFeeLabel
            ]
        );
    }

    public function addSurchargeFeeProductPage($order, $gateway){
        $gatewaySettings = $this->gatewaySettings($gateway);
        if (!isset($gatewaySettings['payment_surcharge']) || $gatewaySettings['payment_surcharge'] == self::NO_FEE) {
           return $order;
        }
        $order->calculate_totals();
        $orderAmount = $order->get_total();

        if($this->aboveMaxLimit($orderAmount, $gatewaySettings)){
            return $order;
        }
        $amount = $this->calculteFeeAmountOrder($order, $gatewaySettings);

        if($amount > 0){
            $surchargeName = $this->buildFeeName($gateway);
            $this->orderRemoveFee($order);
            $this->orderAddFee($order, $amount, $surchargeName);
            $order->calculate_totals();
        }
        return $order;
    }

    public function updateSurchargeOrderPay(){
        $orderId = isset($_POST['orderId'])?filter_var($_POST['orderId'], FILTER_SANITIZE_NUMBER_INT):false;
        if(!$orderId){
            return;
        }
        $order = wc_get_order($orderId);
        if(!$order){
            return;
        }
        $gateway = isset($_POST['method'])?filter_var($_POST['method'], FILTER_SANITIZE_STRING):false;
        if (!$gateway) {
            return;
        }
        if (!$this->isMollieGateway($gateway)) {
            return;
        }
        $this->orderRemoveFee($order);
        $orderAmount = $order->get_total();
        $gatewaySettings = $this->gatewaySettings($gateway);

        if($this->aboveMaxLimit($orderAmount, $gatewaySettings)){
            return;
        }
        if (!isset($gatewaySettings['payment_surcharge']) || $gatewaySettings['payment_surcharge'] == self::NO_FEE) {
            $data= [
                'amount'=>false,
                'currency'=>get_woocommerce_currency_symbol(),
                'newTotal'=>$order->get_total()
            ];
            wp_send_json_success($data);
        }

        $amount = $this->calculteFeeAmountOrder($order, $gatewaySettings);
        $surchargeName = $this->buildFeeName($gateway);


        if($amount > 0){
            $this->orderAddFee($order, $amount, $surchargeName);
            $order->calculate_totals();
            $newTotal = $order->get_total();
            $data= [
                'amount'=>$amount,
                'name'=>$surchargeName,
                'currency'=>get_woocommerce_currency_symbol(),
                'newTotal'=>$newTotal
            ];
            wp_send_json_success($data);
        }
    }

    public function add_engraving_fees( $cart ) {

        if (!mollieWooCommerceIsCheckoutContext()) {
            return;
        }

        $gateway = $this->chosenGateway();
        if (!$gateway) {
            return;
        }

        $gatewaySettings = $this->gatewaySettings($gateway);
        if (!isset($gatewaySettings['payment_surcharge']) || $gatewaySettings['payment_surcharge'] == self::NO_FEE) {
            return;
        }

        $isRecurringCart = ! empty( $cart->recurring_cart_key );
        if ($isRecurringCart) {
            return;
        }

        $cartAmount = $cart->get_cart_total();
        if($this->aboveMaxLimit($cartAmount, $gatewaySettings)){
            return;
        }
        $amount = $this->calculteFeeAmount($cart, $gatewaySettings);
        $surchargeName = $this->buildFeeName($gateway);
        $cart->add_fee( $surchargeName, $amount );
    }

    public function aboveMaxLimit($totalAmount, $gatewaySettings)
    {
        $maxLimit = !empty($gatewaySettings['maximum_limit'])?$gatewaySettings['maximum_limit']:0;
        if ($maxLimit <= 0) {
            return false;
        }
        if($totalAmount > $maxLimit){
            return true;
        }
        return false;
    }

    protected function chosenGateway()
    {
        $gateway = WC()->session->chosen_payment_method;
        if ($gateway === '') {
            $gateway = (!empty($_REQUEST['payment_method'])
                    ? sanitize_text_field(
                            wp_unslash($_REQUEST['payment_method'])
                    ) : '');
        }

        if (!$this->isMollieGateway($gateway)) {
            return false;
        }
        return $gateway;
    }

    protected function isMollieGateway($gateway)
    {
        if (strpos($gateway, 'mollie_wc_gateway_') !== false) {
            return true;
        }
        return false;
    }

    private function gatewaySettings($gateway)
    {
        $optionName = "{$gateway}_settings";
        $allSettings = get_option($optionName, false);
        if (!$allSettings) {
            return false;
        }

        return $allSettings;
    }

    public function calculteFeeAmount($cart, $gatewaySettings)
    {
        $surchargeType = $gatewaySettings['payment_surcharge'];
        $methodName = "calculate_{$surchargeType}";

        return $this->$methodName($cart, $gatewaySettings);
    }

    protected function calculteFeeAmountOrder($cart, $gatewaySettings)
    {
        $surchargeType = $gatewaySettings['payment_surcharge'];
        switch ($surchargeType){
            case 'fixed_fee':
                return $this->calculate_fixed_fee($cart, $gatewaySettings);
            case 'percentage':
                return $this->calculate_percentage_order($cart, $gatewaySettings);
            case 'fixed_fee_percentage':
                return $this->calculate_fixed_fee_percentage_order($cart, $gatewaySettings);
        }

        return 0;
    }

    protected function calculate_no_fee($cart, $gatewaySettings)
    {
        return 0;
    }

    protected function calculate_fixed_fee($cart, $gatewaySettings)
    {
        return !empty($gatewaySettings[self::FIXED_FEE])?(float) $gatewaySettings[self::FIXED_FEE]:0;
    }

    protected function calculate_percentage($cart, $gatewaySettings)
    {
        if(empty($gatewaySettings[self::PERCENTAGE])){
            return 0;
        }
        $percentageFee = $gatewaySettings[self::PERCENTAGE];
        $subtotal = $cart->get_subtotal() + $cart->get_shipping_total() - $cart->get_discount_total();
        $taxes = $cart->get_subtotal_tax() + $cart->get_shipping_tax() - $cart->get_discount_tax();
        $total = $subtotal + $taxes;
        $fee = $total * ($percentageFee / 100);

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    protected function calculate_percentage_order($order, $gatewaySettings)
    {
        if(empty($gatewaySettings[self::PERCENTAGE])){
            return 0;
        }
        $percentageFee = $gatewaySettings[self::PERCENTAGE];
        $total = $order->get_total();
        $fee = $total * ($percentageFee / 100);

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    protected function calculate_fixed_fee_percentage($cart, $gatewaySettings){
        $fixedFee = $this->calculate_fixed_fee($cart, $gatewaySettings);
        $percentageFee = $this->calculate_percentage($cart, $gatewaySettings);
        $fee = $fixedFee + $percentageFee;

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    protected function calculate_fixed_fee_percentage_order($cart, $gatewaySettings){
        $fixedFee = $this->calculate_fixed_fee($cart, $gatewaySettings);
        $percentageFee = $this->calculate_percentage_order($cart, $gatewaySettings);
        $fee = $fixedFee + $percentageFee;

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    /**
     * @param string $gateway
     *
     * @return string
     */
    protected function buildFeeName($gateway)
    {

        return __($this->gatewayFeeLabel, 'mollie-payments-for-woocommerce');
    }

    protected function addMaxLimit($fee, $gatewaySettings)
    {
        if (empty($gatewaySettings['surcharge_limit'])) {
            return $fee;
        }
        $maxLimit = $gatewaySettings['surcharge_limit'];
        if ($fee > $maxLimit) {
            return $maxLimit;
        }
        return $fee;
    }

    /**
    * @var wc_order $order
     */
    protected function orderRemoveFee($order)
    {
        $fees = $order->get_fees();
        foreach ($fees as $fee){
            $feeName = $fee->get_name();
            $feeId = $fee->get_id();
            if(strpos($feeName, $this->gatewayFeeLabel) !== false){
                $order->remove_item($feeId);
                wc_delete_order_item( $feeId );
                $order->calculate_totals();
            }
        }
    }

    protected function orderAddFee($order, $amount, $surchargeName)
    {
        $item_fee = new WC_Order_Item_Fee();
        $item_fee->set_name( $surchargeName );
        $item_fee->set_amount( $amount );
        $item_fee->set_total( $amount );
        $item_fee->set_tax_status('none');
        $order->add_item( $item_fee );
        $order->calculate_totals();
    }
}

