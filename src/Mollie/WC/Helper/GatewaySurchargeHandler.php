<?php

class Mollie_WC_Helper_GatewaySurchargeHandler
{
    const NO_FEE = 'no_fee';
    const FIXED_FEE = 'fixed_fee';
    const PERCENTAGE = 'percentage';
    const FIXED_AND_PERCENTAGE = 'fixed_fee_percentage';
    /**
     * @var Mollie_WC_Notice_AdminNotice
     */
    private $adminNotice;


    /**
     * Mollie_WC_Helper_ApplePayDirectHandler constructor.
     *
     * @param Mollie_WC_Notice_AdminNotice              $notice
     *
     */
    public function __construct(Mollie_WC_Notice_AdminNotice $notice)
    {
        $this->adminNotice = $notice;
        add_filter( 'woocommerce_cart_calculate_fees', [$this, 'add_engraving_fees'], 10, 1 );
        add_action( 'wp_enqueue_scripts', [$this, 'enqueueSurchargeScript' ]);

    }

    public function enqueueSurchargeScript()
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        wp_enqueue_script('gatewaySurcharge');
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
        if (!$gatewaySettings['payment_surcharge'] || $gatewaySettings['payment_surcharge'] == self::NO_FEE) {
            return;
        }
        // ! empty( $cart->recurring_cart_key ) es recurring no quiero  WC_Subscriptions_Cart::cart_contains_subscription();
        $isRecurringCart = ! empty( $cart->recurring_cart_key );
        if ($isRecurringCart) {
            return;
        }
        $amount = $this->calculteFeeAmount($cart, $gatewaySettings);


        // el nombre es el de la gateway mas surcharge fee
        $surchargeName = $this->buildFeeName($gateway);
        //en el notice creo que puedo enseñar lo que añadirá, cuando lo selecciona o en todos?si lo hago con js seguro
        $cart->add_fee( $surchargeName, $amount );
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

    protected function calculteFeeAmount($cart, $gatewaySettings)
    {
        $surgargeType = $gatewaySettings['payment_surcharge'];
        $methodName = "calculate_{$surgargeType}";
        $fee = $this->$methodName($cart, $gatewaySettings);
        $fee = $this->addMaxLimit($fee, $gatewaySettings);

        return $fee;
    }

    protected function calculate_fixed_fee($cart, $gatewaySettings)
    {
        return $gatewaySettings['fixed_fee'];
    }

    protected function calculate_percentage($cart, $gatewaySettings)
    {
        $percentageFee = $gatewaySettings['percentage'];
        $subtotal = $cart->get_subtotal() + $cart->get_shipping_total() - $cart->get_discount_total();
        $taxes = $cart->get_subtotal_tax() + $cart->get_shipping_total() - $cart->get_discount_tax();
        $total = $subtotal + $taxes;

        return $total * ($percentageFee / 100);
    }

    protected function calculate_fixed_fee_percentage($cart, $gatewaySettings){
        $fixedFee = $this->calculate_fixed_fee($cart, $gatewaySettings);
        $percentageFee = $this->calculate_percentage($cart, $gatewaySettings);

        return $fixedFee + $percentageFee;
    }

    /**
     * @param string $gateway
     *
     * @return string
     */
    protected function buildFeeName($gateway)
    {
        $gatewayName = strtoupper(
                str_replace('mollie_wc_gateway_', '', $gateway)
        );

        return "{$gatewayName} Gateway Fee";
    }

    protected function addMaxLimit($fee, $gatewaySettings)
    {
        $maxLimit = $gatewaySettings['surcharge_limit'];
        if ($fee > $maxLimit) {
            return $maxLimit;
        }
        return $fee;
    }
}

