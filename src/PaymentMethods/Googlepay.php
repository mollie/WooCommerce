<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Psr\Container\ContainerInterface;

/**
 * Google Pay exists only so the Express Component can offer it: Mollie cannot take a standalone
 * Google Pay payment, so this method is never offered at checkout and not registered for Blocks.
 * Its one setting, `enabled`, is also its express setting in config/express.php.
 *
 * Extends AbstractPaymentMethod as a recorded exception (docs/architecture/express-component.md, section 7):
 * a gateway is built from its class name until method rows (ADR-003) exist.
 */
class Googlepay extends AbstractPaymentMethod implements PaymentMethodI
{
    public function getConfig(): array
    {
        return [
            'id' => 'googlepay',
            'defaultTitle' => 'Google Pay',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'docs' => 'https://www.mollie.com/payments/googlepay',
        ];
    }

    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Google Pay', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        return [
            'enabled' => array_merge($generalFormFields['enabled'], [
                'label' => __('Show Google Pay in express checkout on the checkout page', 'mollie-payments-for-woocommerce'),
                'default' => 'no',
            ]),
        ];
    }

    public function availabilityCallback(ContainerInterface $container): callable
    {
        return static fn (): bool => false;
    }

    public function registerBlocks(ContainerInterface $container): bool
    {
        return false;
    }
}
