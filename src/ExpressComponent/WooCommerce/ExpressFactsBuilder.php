<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
use Mollie\WooCommerce\Settings\Settings;

/**
 * Express has no option of its own: it reads each wallet's gateway settings, afresh on every call.
 */
class ExpressFactsBuilder
{
    private const GATEWAY_PREFIX = 'mollie_wc_gateway_';

    /**
     * @param array{wallets: array<string, array{gatewayId: string, mollieMethod: string, paidAs: string, checkoutSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>} $config
     * @param array<string, mixed> $paymentMethods Keyed by Mollie method id.
     * @param callable(): array<int, string> $activeMollieMethods Cached method ids active on the profile.
     */
    public function __construct(
        private array $config,
        private Settings $settings,
        private array $paymentMethods,
        private $activeMollieMethods
    ) {
    }

    public function settings(): ExpressSettings
    {
        return new ExpressSettings(
            supportedSurfaces: array_values($this->config['surfaces']),
            allowedModes: array_values($this->config['allowedModes']),
            wallets: $this->config['wallets']
        );
    }

    public function shopFacts(): ShopFacts
    {
        [$registered, $enabled, $expressOnCheckout, $surcharged] = $this->merchantSettings();

        return new ShopFacts(
            mode: $this->mode(),
            isHttps: wc_site_is_https(),
            registeredGatewayIds: $registered,
            enabledGatewayIds: $enabled,
            activeMollieMethods: $this->activeMollieMethods(),
            expressCheckoutGatewayIds: $expressOnCheckout,
            surchargedGatewayIds: $surcharged
        );
    }

    /**
     * @return 'test'|'live' The mode whose key asks Mollie.
     */
    public function mode(): string
    {
        return $this->settings->isTestModeEnabled() ? 'test' : 'live';
    }

    /** Options only, no Mollie call: cheap enough to run on every request. */
    public function anyWalletTurnedOn(): bool
    {
        [, $enabled, $expressOnCheckout, $surcharged] = $this->merchantSettings();

        return array_diff(array_intersect($enabled, $expressOnCheckout), $surcharged) !== [];
    }

    /**
     * Registered, enabled, express-on-checkout and surcharged gateway ids.
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>, 3: list<string>}
     */
    private function merchantSettings(): array
    {
        $registered = [];
        foreach (array_keys($this->paymentMethods) as $methodId) {
            $registered[] = self::GATEWAY_PREFIX . $methodId;
        }

        $enabled = [];
        $expressOnCheckout = [];
        $surcharged = [];
        foreach ($this->config['wallets'] as $row) {
            $gatewayId = $row['gatewayId'];
            if (!in_array($gatewayId, $registered, true)) {
                continue;
            }
            $settingsOption = $gatewayId . '_settings';
            if (mollieWooCommerceIsGatewayEnabled($settingsOption, 'enabled')) {
                $enabled[] = $gatewayId;
            }
            if (mollieWooCommerceIsGatewayEnabled($settingsOption, $row['checkoutSetting'])) {
                $expressOnCheckout[] = $gatewayId;
            }
            $surcharge = ((array) get_option($settingsOption, []))['payment_surcharge'] ?? '';
            if ($surcharge !== '' && $surcharge !== Surcharge::NO_FEE) {
                $surcharged[] = $gatewayId;
            }
        }

        return [$registered, $enabled, $expressOnCheckout, $surcharged];
    }

    /**
     * @return list<string>
     */
    private function activeMollieMethods(): array
    {
        $active = [];
        foreach (($this->activeMollieMethods)() as $methodId) {
            if (is_string($methodId)) {
                $active[] = $methodId;
            }
        }

        return $active;
    }
}
