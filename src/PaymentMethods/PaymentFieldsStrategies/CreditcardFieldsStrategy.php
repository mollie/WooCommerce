<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;

class CreditcardFieldsStrategy implements PaymentFieldsStrategyI
{

    public function execute($gateway, $dataHelper)
    {
        if (!$this->isMollieComponentsEnabled($gateway->paymentMethod)) {
            return;
        }
        $gateway->has_fields = true;

        ?>
        <div class="mollie-components"></div>
        <p class="mollie-components-description">
            <?php
            printf(
            /* translators: Placeholder 1: Lock icon. Placeholder 2: Mollie logo. */
            __('%1$s Secure payments provided by %2$s',
                    'mollie-payments-for-woocommerce'),
                $this->lockIcon($dataHelper),
                $this->mollieLogo($dataHelper)
            );
            ?>
        </p>
        <?php
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        if (!$this->isMollieComponentsEnabled($gateway->paymentMethod)) {
            return false;
        }
        $gateway->has_fields = true;
        $descriptionTranslated = __('Secure payments provided by', 'mollie-payments-for-woocommerce');
        $componentsDescription = "{$this->lockIcon($dataHelper)} {$descriptionTranslated} {$this->mollieLogo($dataHelper)}";
        return "<div class='payment_method_mollie_wc_gateway_creditcard'><div class='mollie-components'></div><p class='mollie-components-description'>{$componentsDescription}</p></div>";
    }

    protected function isMollieComponentsEnabled(PaymentMethodI $paymentMethod): bool
    {
        return $paymentMethod->hasPaymentFields();
    }

    protected function lockIcon($dataHelper)
    {
        return file_get_contents(
            $dataHelper->pluginPath . '/' . 'public/images/lock-icon.svg'
        );
    }

    protected function mollieLogo($dataHelper)
    {
        return file_get_contents(
            $dataHelper->pluginPath . '/' . 'public/images/mollie-logo.svg'
        );
    }
}
