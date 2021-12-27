<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;

class SurchargeService
{

    public function buildDescriptionWithSurcharge(PaymentMethodI $paymentMethod)
    {
        $defaultDescription = $paymentMethod->getProperty('defaultDescription') ?: '';
        $surchargeType = $paymentMethod->getProperty('payment_surcharge');
        if (!mollieWooCommerceIsCheckoutContext()) {
            return $defaultDescription;
        }
        if (
            !$surchargeType
            || $surchargeType === GatewaySurchargeHandler::NO_FEE
        ) {
            return $defaultDescription;
        }

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
        if ($feeText) {
            $feeLabel = '<span class="mollie-gateway-fee">' . $feeText . '</span>';
            return $defaultDescription . $feeLabel;
        }
        return $defaultDescription;
    }

    protected function name_fixed_fee($paymentMethod)
    {
        if (
            !$paymentMethod->getProperty(GatewaySurchargeHandler::FIXED_FEE)
            || $paymentMethod->getProperty(GatewaySurchargeHandler::FIXED_FEE) <= 0
        ) {
            return false;
        }
        $amountFee = $paymentMethod->getProperty(GatewaySurchargeHandler::FIXED_FEE);
        $currency = get_woocommerce_currency_symbol();
        return sprintf(__(" +%1\$1s%2\$2s fee might apply", 'mollie-payments-for-woocommerce'), $amountFee, $currency);
    }

    protected function name_percentage($paymentMethod)
    {
        if (
            !$paymentMethod->getProperty(GatewaySurchargeHandler::PERCENTAGE)
            || $paymentMethod->getProperty(GatewaySurchargeHandler::PERCENTAGE) <= 0
        ) {
            return false;
        }
        $amountFee = $paymentMethod->getProperty(GatewaySurchargeHandler::PERCENTAGE);
        return sprintf(__(' +%1s%% fee might apply', 'mollie-payments-for-woocommerce'), $amountFee);
    }

    protected function name_fixed_fee_percentage($paymentMethod)
    {
        if (
            !$paymentMethod->getProperty(GatewaySurchargeHandler::FIXED_FEE)
            || !$paymentMethod->getProperty(GatewaySurchargeHandler::PERCENTAGE)
            || $paymentMethod->getProperty(GatewaySurchargeHandler::FIXED_FEE) === ''
            || $paymentMethod->getProperty(GatewaySurchargeHandler::PERCENTAGE) === ''
            || $paymentMethod->getProperty(GatewaySurchargeHandler::PERCENTAGE) <= 0
            || $paymentMethod->getProperty(GatewaySurchargeHandler::FIXED_FEE) <= 0
        ) {
            return false;
        }
        $amountFix = $paymentMethod->getProperty(GatewaySurchargeHandler::FIXED_FEE);
        $currency = get_woocommerce_currency_symbol();
        $amountPercent = $paymentMethod->getProperty(GatewaySurchargeHandler::PERCENTAGE);
        return sprintf(__(" +%1\$1s%2\$2s + %3\$3s%% fee might apply", 'mollie-payments-for-woocommerce'), $amountFix, $currency, $amountPercent);
    }
}
