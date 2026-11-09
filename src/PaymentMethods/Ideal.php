<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Ideal extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected const DEFAULT_ISSUERS_DROPDOWN = 'yes';
    public function getConfig(): array
    {
        return ['id' => 'ideal', 'defaultTitle' => 'iDEAL', 'settingsDescription' => '', 'defaultDescription' => '', 'paymentFields' => \false, 'instructions' => \true, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \true, 'SEPA' => \true, 'docs' => 'https://www.mollie.com/gb/payments/ideal-2-0'];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('iDEAL', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        $notice = ['notice' => ['title' => sprintf(
            /* translators: Placeholder 1: paragraph opening tag Placeholder 2: link url Placeholder 3: link closing tag 4: link url Placeholder  5: closing tags */
            __('%1$s Note: In June 2024, Mollie upgraded its iDEAL implementation to iDEAL 2.0.
                            As a result, the bank selector dropdown is no longer displayed on the checkout page when using the Mollie plugin.
                            Buyers will now select their bank directly on the iDEAL website.
                            The only action required from you is to update your iDEAL gateway description to remove any prompts for buyers to select a bank during checkout.
                            No further manual action is needed. For more details about the iDEAL 2.0 migration, please visit the
                            %2$s Mollie Help Center %3$s or read this
                            %4$s this blog post. %5$s', 'mollie-payments-for-woocommerce'),
            '<p>',
            '<a href="https://help.mollie.com/hc/en-us/articles/19100313768338-iDEAL-2-0" target="_blank">',
            '</a>',
            '<a href="https://www.mollie.com/growth/ideal-2-0" target="_blank">',
            '</a></p>'
        ), 'type' => 'title', 'class' => 'notice notice-warning', 'css' => 'padding:20px;']];
        return array_merge($notice, $generalFormFields);
    }
}
