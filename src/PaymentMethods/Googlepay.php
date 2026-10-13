<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

use Mollie\Psr\Container\ContainerInterface;
/**
 * Only for the Express Component: Mollie takes no standalone Google Pay payment, so it is never
 * offered at checkout.
 */
class Googlepay extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    public function getConfig(): array
    {
        return ['id' => 'googlepay', 'defaultTitle' => 'Google Pay', 'settingsDescription' => '', 'defaultDescription' => '', 'paymentFields' => \false, 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \false, 'docs' => 'https://www.mollie.com/payments/googlepay'];
    }
    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Google Pay', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        return ['enabled' => array_merge($generalFormFields['enabled'], ['label' => __('Show Google Pay in express checkout on the checkout page', 'mollie-payments-for-woocommerce'), 'default' => 'no'])];
    }
    public function availabilityCallback(ContainerInterface $container): callable
    {
        return static fn(): bool => \false;
    }
    public function registerBlocks(ContainerInterface $container): bool
    {
        return \false;
    }
}
