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
                __(esc_html('%1$s Secure payments provided by %2$s'),
                   'mollie-payments-for-woocommerce'),
                $this->lockIcon($gateway),
                $this->mollieLogo($gateway)
            );
            ?>
        </p>
        <?php
    }

    protected function isMollieComponentsEnabled(PaymentMethodI $paymentMethod): bool
    {
        return $paymentMethod->getProperty('mollie_components_enabled') === 'yes';
    }

    protected function lockIcon($gateway)
    {
        return file_get_contents(
            $gateway->pluginPath . '/' .  'public/images/lock-icon.svg'
        );
    }

    protected function mollieLogo($gateway)
    {
        return file_get_contents(
            $gateway->pluginPath . '/' .  'public/images/mollie-logo.svg'
        );
    }

}
