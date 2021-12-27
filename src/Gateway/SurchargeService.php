<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;

class SurchargeService
{

    public function buildDescriptionWithSurcharge(PaymentMethodI $paymentMethod)
    {
        $defaultDescription = $paymentMethod->getProperty('defaultDescription')?:'';
        $surchargeType = $paymentMethod->getProperty('payment_surcharge');
        if (!mollieWooCommerceIsCheckoutContext()) {
            return $defaultDescription;
        }
        if (!$surchargeType
            || $surchargeType === GatewaySurchargeHandler::NO_FEE
        ) {
            return $defaultDescription;
        }

        switch ($surchargeType) {
            case 'fixed_fee':
                $feeText = $this->name_fixed_fee();
                break;
            case 'percentage':
                $feeText = $this->name_percentage();
                break;
            case 'fixed_fee_percentage':
                $feeText = $this->name_fixed_fee_percentage();
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

    protected function name_fixed_fee()
    {
        if (!isset($gateway->settings[GatewaySurchargeHandler::FIXED_FEE])
            || $gateway->settings[GatewaySurchargeHandler::FIXED_FEE] <= 0) {
            return false;
        }
        $amountFee = $gateway->settings[GatewaySurchargeHandler::FIXED_FEE];
        $currency = get_woocommerce_currency_symbol();
        return sprintf(__(" +%1\$1s%2\$2s fee might apply", 'mollie-payments-for-woocommerce'), $amountFee, $currency);
    }

    protected function name_percentage()
    {
        if (!isset($gateway->settings[GatewaySurchargeHandler::PERCENTAGE])
            || $gateway->settings[GatewaySurchargeHandler::PERCENTAGE] <= 0) {
            return false;
        }
        $amountFee = $gateway->settings[GatewaySurchargeHandler::PERCENTAGE];
        return sprintf(__(' +%1s%% fee might apply', 'mollie-payments-for-woocommerce'), $amountFee);
    }

    protected function name_fixed_fee_percentage()
    {
        if (!isset($gateway->settings[GatewaySurchargeHandler::FIXED_FEE])
            || !isset($gateway->settings[GatewaySurchargeHandler::PERCENTAGE])
            || $gateway->settings[GatewaySurchargeHandler::FIXED_FEE] == ''
            || $gateway->settings[GatewaySurchargeHandler::PERCENTAGE] == ''
            || $gateway->settings[GatewaySurchargeHandler::PERCENTAGE] <= 0
            || $gateway->settings[GatewaySurchargeHandler::FIXED_FEE] <= 0
        ) {
            return false;
        }
        $amountFix = $gateway->settings[GatewaySurchargeHandler::FIXED_FEE];
        $currency = get_woocommerce_currency_symbol();
        $amountPercent = $gateway->settings[GatewaySurchargeHandler::PERCENTAGE];
        return sprintf(__(" +%1\$1s%2\$2s + %3\$3s%% fee might apply", 'mollie-payments-for-woocommerce'), $amountFix, $currency, $amountPercent);
    }
}
