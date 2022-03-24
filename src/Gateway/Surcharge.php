<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;

class Surcharge
{

    /**
     * @var string
     */
    public const FIXED_FEE = 'fixed_fee';
    public const DEFAULT_FEE_LABEL = 'Gateway Fee';
    /**
     * @var string
     */
    public const NO_FEE = 'no_fee';
    /**
     * @var string
     */
    public const PERCENTAGE = 'percentage';
    /**
     * @var string
     */
    public const FIXED_AND_PERCENTAGE = 'fixed_fee_percentage';



    public function buildDescriptionWithSurcharge($description, PaymentMethodI $paymentMethod)
    {
        $defaultDescription = $description;
        $surchargeType = $paymentMethod->getProperty('payment_surcharge');

        if (
            !$surchargeType
            || $surchargeType === self::NO_FEE
        ) {
            return $defaultDescription;
        }

        $feeText = $this->feeTextByType($surchargeType, $paymentMethod);
        if ($feeText) {
            $feeLabel = '<span class="mollie-gateway-fee">' . $feeText . '</span>';
            return $defaultDescription . $feeLabel;
        }
        return $defaultDescription;
    }

    public function buildDescriptionWithSurchargeForBlock(PaymentMethodI $paymentMethod)
    {
        $defaultDescription = $paymentMethod->getProperty('description') ?: '';
        $surchargeType = $paymentMethod->getProperty('payment_surcharge');

        if (
            !$surchargeType
            || $surchargeType === self::NO_FEE
        ) {
            return $defaultDescription;
        }
        $feeText = $this->feeTextByType($surchargeType, $paymentMethod);
        $feeText = html_entity_decode($feeText);

        return $feeText?:__('A surchage fee might apply');
    }

    public function aboveMaxLimit($totalAmount, $gatewaySettings)
    {
        $maxLimit = !empty($gatewaySettings['maximum_limit']) ? $gatewaySettings['maximum_limit'] : 0;
        if ($maxLimit <= 0) {
            return false;
        }
        if ($totalAmount > $maxLimit) {
            return true;
        }
        return false;
    }

    public function calculateFeeAmount($cart, $gatewaySettings)
    {
        $surchargeType = $gatewaySettings['payment_surcharge'];
        $methodName = sprintf('calculate_%s', $surchargeType);

        if (!method_exists($this, $methodName)) {
            return 0;
        }
        return $this->$methodName($cart, $gatewaySettings);
    }

    public function calculateFeeAmountOrder($cart, $gatewaySettings)
    {
        $surchargeType = isset($gatewaySettings['payment_surcharge'])?$gatewaySettings['payment_surcharge']:'';
        switch ($surchargeType) {
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
        return !empty($gatewaySettings[Surcharge::FIXED_FEE]) ? (float) $gatewaySettings[Surcharge::FIXED_FEE] : 0;
    }

    protected function calculate_percentage($cart, $gatewaySettings)
    {
        if (empty($gatewaySettings[Surcharge::PERCENTAGE])) {
            return 0;
        }
        $percentageFee = $gatewaySettings[Surcharge::PERCENTAGE];
        $subtotal = $cart->get_subtotal() + $cart->get_shipping_total() - $cart->get_discount_total();
        $taxes = $cart->get_subtotal_tax() + $cart->get_shipping_tax() - $cart->get_discount_tax();
        $total = $subtotal + $taxes;
        $fee = $total * ($percentageFee / 100);

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    protected function calculate_percentage_order($order, $gatewaySettings)
    {
        if (empty($gatewaySettings[Surcharge::PERCENTAGE])) {
            return 0;
        }
        $percentageFee = $gatewaySettings[Surcharge::PERCENTAGE];
        $total = $order->get_total();
        $fee = $total * ($percentageFee / 100);

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    protected function calculate_fixed_fee_percentage($cart, $gatewaySettings)
    {
        $fixedFee = $this->calculate_fixed_fee($cart, $gatewaySettings);
        $percentageFee = $this->calculate_percentage($cart, $gatewaySettings);
        $fee = $fixedFee + $percentageFee;

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    protected function calculate_fixed_fee_percentage_order($cart, $gatewaySettings)
    {
        $fixedFee = $this->calculate_fixed_fee($cart, $gatewaySettings);
        $percentageFee = $this->calculate_percentage_order($cart, $gatewaySettings);
        $fee = $fixedFee + $percentageFee;

        return $this->addMaxLimit($fee, $gatewaySettings);
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
     * @param string $gateway
     *
     * @return string
     */
    public function buildFeeName($gateway)
    {
        return __($gateway, 'mollie-payments-for-woocommerce');
    }


    protected function name_fixed_fee($paymentMethod)
    {
        if (
            !$paymentMethod->getProperty(self::FIXED_FEE)
            || $paymentMethod->getProperty(self::FIXED_FEE) <= 0
        ) {
            return false;
        }
        $amountFee = $paymentMethod->getProperty(self::FIXED_FEE);
        $currency = get_woocommerce_currency_symbol();
        /* translators: Placeholder 1: Fee amount tag. Placeholder 2: Currency.*/
        return sprintf(__(' +%1s%2s fee might apply', 'mollie-payments-for-woocommerce'), $amountFee, $currency);
    }

    protected function name_percentage($paymentMethod)
    {
        if (
            !$paymentMethod->getProperty(self::PERCENTAGE)
            || $paymentMethod->getProperty(self::PERCENTAGE) <= 0
        ) {
            return false;
        }
        $amountFee = $paymentMethod->getProperty(self::PERCENTAGE);
        /* translators: Placeholder 1: Fee amount tag.*/
        return sprintf(__(' +%1s%% fee might apply', 'mollie-payments-for-woocommerce'), $amountFee);
    }

    protected function name_fixed_fee_percentage($paymentMethod)
    {
        if (
            !$paymentMethod->getProperty(self::FIXED_FEE)
            || !$paymentMethod->getProperty(self::PERCENTAGE)
            || $paymentMethod->getProperty(self::FIXED_FEE) === ''
            || $paymentMethod->getProperty(self::PERCENTAGE) === ''
            || $paymentMethod->getProperty(self::PERCENTAGE) <= 0
            || $paymentMethod->getProperty(self::FIXED_FEE) <= 0
        ) {
            return false;
        }
        $amountFix = $paymentMethod->getProperty(self::FIXED_FEE);
        $currency = get_woocommerce_currency_symbol();
        $amountPercent = $paymentMethod->getProperty(self::PERCENTAGE);
        /* translators: Placeholder 1: Fee amount tag. Placeholder 2: Currency. Placeholder 3: Percentage amount. */
        return sprintf(__(' +%1s%2s + %3s%% fee might apply', 'mollie-payments-for-woocommerce'), $amountFix, $currency, $amountPercent);
    }

    /**
     * @param $surchargeType
     * @param PaymentMethodI $paymentMethod
     * @return false|string
     */
    protected function feeTextByType($surchargeType, PaymentMethodI $paymentMethod)
    {
        switch ($surchargeType) {
            case 'fixed_fee':
                $feeText = $this->name_fixed_fee($paymentMethod);
                break;
            case 'percentage':
                $feeText = $this->name_percentage($paymentMethod);
                break;
            case 'fixed_fee_percentage':
                $feeText = $this->name_fixed_fee_percentage($paymentMethod);
                break;
            default:
                $feeText = false;
        }
        return $feeText;
    }
}
