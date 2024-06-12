<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use WC_Cart;
use WC_Order;

class Surcharge
{
    /**
     * @var string
     */
    public const FIXED_FEE = 'fixed_fee';

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

    /**
     * @return string
     */
    public function defaultFeeLabel(): string
    {
        return __('Gateway Fee', 'mollie-payments-for-woocommerce');
    }

    /**
     * @param $description
     * @param PaymentMethodI $paymentMethod
     * @return mixed|string
     */
    public function buildDescriptionWithSurcharge($description, PaymentMethodI $paymentMethod)
    {
        $surchargeType = $paymentMethod->getProperty('payment_surcharge');

        if (
            !$surchargeType
            || $surchargeType === self::NO_FEE
        ) {
            return $description;
        }

        $feeText = $this->feeTextByType($surchargeType, $paymentMethod);
        if ($feeText) {
            $feeLabel = '<p class="mollie-gateway-fee">' . $feeText . '</p>';
            return $description . $feeLabel;
        }
        return $description;
    }

    /**
     * @param PaymentMethodI $paymentMethod
     * @return string
     */
    public function buildDescriptionWithSurchargeForBlock(PaymentMethodI $paymentMethod)
    {
        $defaultDescription = $paymentMethod->getProperty('description') ?: ($paymentMethod->getProperty('defaultDescription') ?: '');
        $surchargeType = $paymentMethod->getProperty('payment_surcharge');

        if (
            !$surchargeType
            || $surchargeType === self::NO_FEE
        ) {
            return $defaultDescription;
        }
        $feeText = $this->feeTextByType($surchargeType, $paymentMethod);
        $feeText = is_string($feeText) ? $defaultDescription . ' ' . html_entity_decode($feeText) : false;
        $defaultFeeText = $defaultDescription . ' ' . __('A surchage fee might apply');

        return $feeText ?: $defaultFeeText;
    }

    /**
     * @param float $totalAmount
     * @param array $gatewaySettings
     * @return bool
     */
    public function aboveMaxLimit(float $totalAmount, array $gatewaySettings): bool
    {
        $maxLimit = !empty($gatewaySettings['maximum_limit']) ? $gatewaySettings['maximum_limit'] : 0.0;
        if ($maxLimit <= 0.0) {
            return false;
        }
        if ($totalAmount > $maxLimit) {
            return true;
        }
        return false;
    }

    /**
     * @param WC_Cart $cart
     * @param array $gatewaySettings
     * @return float
     */
    public function calculateFeeAmount(WC_Cart $cart, array $gatewaySettings): float
    {
        if (!isset($gatewaySettings['payment_surcharge'])) {
            return 0.0;
        }

        $surchargeType = $gatewaySettings['payment_surcharge'];
        $methodName = sprintf('calculate_%s', $surchargeType);

        if (!method_exists($this, $methodName)) {
            return 0.0;
        }
        return $this->$methodName($cart, $gatewaySettings);
    }

    /**
     * @param WC_Order $order
     * @param array $gatewaySettings
     * @return float|int|mixed
     */
    public function calculateFeeAmountOrder(WC_Order $order, array $gatewaySettings)
    {
        $surchargeType = $gatewaySettings['payment_surcharge'] ?? '';
        $amount = 0.0;
        switch ($surchargeType) {
            case 'fixed_fee':
                $amount = $this->calculate_fixed_fee($order, $gatewaySettings);
                break;
            case 'percentage':
                $amount = $this->calculate_percentage_order($order, $gatewaySettings);
                break;
            case 'fixed_fee_percentage':
                $amount = $this->calculate_fixed_fee_percentage_order($order, $gatewaySettings);
                break;
        }
        return $amount;
    }

    /**
     * @param WC_Cart $cart
     * @param array $gatewaySettings
     * @return float
     */
    protected function calculate_no_fee(WC_Cart $cart, array $gatewaySettings): float
    {
        return 0.0;
    }

    /**
     * @param WC_Cart|WC_Order $cart
     * @param array $gatewaySettings
     * @return float|int
     */
    protected function calculate_fixed_fee($cart, array $gatewaySettings)
    {
        return !empty($gatewaySettings[Surcharge::FIXED_FEE]) ? (float)$gatewaySettings[Surcharge::FIXED_FEE] : 0.0;
    }

    /**
     * @param WC_Cart $cart
     * @param array $gatewaySettings
     * @return int|mixed
     */
    protected function calculate_percentage(WC_Cart $cart, array $gatewaySettings)
    {
        if (empty($gatewaySettings[Surcharge::PERCENTAGE])) {
            return 0.0;
        }
        $percentageFee = (float) $gatewaySettings[Surcharge::PERCENTAGE];
        $subtotal = $cart->get_subtotal() + $cart->get_shipping_total() - $cart->get_discount_total();
        $taxes = $cart->get_subtotal_tax() + $cart->get_shipping_tax() - $cart->get_discount_tax();
        $total = $subtotal + $taxes;
        $fee = $total * ($percentageFee / 100);

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    /**
     * @param WC_Order $order
     * @param array $gatewaySettings
     * @return float|mixed
     */
    protected function calculate_percentage_order(WC_Order $order, array $gatewaySettings)
    {
        if (empty($gatewaySettings[Surcharge::PERCENTAGE])) {
            return 0.0;
        }
        $percentageFee = (float) $gatewaySettings[Surcharge::PERCENTAGE];
        $total = $order->get_total();
        $fee = $total * ($percentageFee / 100);

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    /**
     * @param WC_Cart $cart
     * @param array $gatewaySettings
     * @return mixed
     */
    protected function calculate_fixed_fee_percentage(WC_Cart $cart, array $gatewaySettings)
    {
        $fixedFee = $this->calculate_fixed_fee($cart, $gatewaySettings);
        $percentageFee = $this->calculate_percentage($cart, $gatewaySettings);
        $fee = $fixedFee + $percentageFee;

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    /**
     * @param WC_Order $order
     * @param array $gatewaySettings
     *
     * @return mixed
     */
    protected function calculate_fixed_fee_percentage_order(WC_Order $order, array $gatewaySettings)
    {
        $fixedFee = $this->calculate_fixed_fee($order, $gatewaySettings);
        $percentageFee = $this->calculate_percentage_order($order, $gatewaySettings);
        $fee = $fixedFee + $percentageFee;

        return $this->addMaxLimit($fee, $gatewaySettings);
    }

    /**
     * @param float $fee
     * @param array $gatewaySettings
     * @return float
     */
    protected function addMaxLimit(float $fee, array $gatewaySettings): float
    {
        if (empty($gatewaySettings['surcharge_limit'])) {
            return $fee;
        }
        $maxLimit = (float)$gatewaySettings['surcharge_limit'];
        if ($fee > $maxLimit) {
            return $maxLimit;
        }
        return $fee;
    }

    /**
     * @param $paymentMethod
     * @return false|string
     */
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
        return sprintf(__(' + %1$s %2$s fee might apply', 'mollie-payments-for-woocommerce'), $currency, $amountFee);
    }

    /**
     * @param $paymentMethod
     * @return false|string
     */
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
        return sprintf(__(' + %1$s%% fee might apply', 'mollie-payments-for-woocommerce'), $amountFee);
    }

    /**
     * @param $paymentMethod
     * @return false|string
     */
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
        return sprintf(
            __(' + %1$s %2$s + %3$s%% fee might apply', 'mollie-payments-for-woocommerce'),
            $currency,
            $amountFix,
            $amountPercent
        );
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
        return $feeText ? $this->maybeAddTaxString($feeText) : false;
    }

    /**
     * @param string $feeText
     * @return string
     */
    protected function maybeAddTaxString(string $feeText): string
    {
        if (wc_tax_enabled()) {
            $feeText .= __(' (excl. VAT)', 'mollie-payments-for-woocommerce');
        }
        return $feeText;
    }
}
