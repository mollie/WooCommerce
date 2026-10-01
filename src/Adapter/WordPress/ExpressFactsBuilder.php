<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WordPress;

use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;
use Mollie\WooCommerce\Settings\Settings;

/**
 * Translates WordPress and WooCommerce state into the plain values the express decisions read.
 *
 * Express has no option of its own. What the merchant turned on is read where they already turn it
 * on: each wallet's payment method settings ("enabled", and its "show the express button on the
 * checkout" setting). Every call reads the mode, the scheme and those settings afresh, so a change
 * is seen at once and nothing is memoised here.
 */
class ExpressFactsBuilder
{
    private const GATEWAY_PREFIX = 'mollie_wc_gateway_';

    /**
     * @param array{wallets: array<string, array{gatewayId: string, mollieMethod: string, needsHttps: bool, checkoutSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>} $config
     * @param array<string, mixed> $paymentMethods The plugin's payment methods, keyed by Mollie method id.
     * @param callable(): array<int, string> $activeMollieMethods Mollie method ids active on the merchant's
     *        profile, from the list the plugin already fetches and caches (this class never sees the API key).
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
        $registered = [];
        foreach (array_keys($this->paymentMethods) as $methodId) {
            $registered[] = self::GATEWAY_PREFIX . $methodId;
        }

        $enabled = [];
        $expressOnCheckout = [];
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
        }

        return new ShopFacts(
            mode: $this->settings->isTestModeEnabled() ? 'test' : 'live',
            isHttps: wc_site_is_https(),
            registeredGatewayIds: $registered,
            enabledGatewayIds: $enabled,
            activeMollieMethods: $this->activeMollieMethods(),
            expressCheckoutGatewayIds: $expressOnCheckout
        );
    }

    /**
     * Whether the merchant turned Express on for any wallet: its payment method exists and is enabled,
     * and its "show the express button on the checkout" setting is on. Options only, no Mollie call,
     * so it is cheap enough for every request (the unpaid-orders schedule asks it on init).
     */
    public function anyWalletTurnedOn(): bool
    {
        foreach ($this->config['wallets'] as $row) {
            $methodId = substr($row['gatewayId'], strlen(self::GATEWAY_PREFIX));
            if (!array_key_exists($methodId, $this->paymentMethods)) {
                continue;
            }
            $settingsOption = $row['gatewayId'] . '_settings';
            if (
                mollieWooCommerceIsGatewayEnabled($settingsOption, 'enabled')
                && mollieWooCommerceIsGatewayEnabled($settingsOption, $row['checkoutSetting'])
            ) {
                return true;
            }
        }

        return false;
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
