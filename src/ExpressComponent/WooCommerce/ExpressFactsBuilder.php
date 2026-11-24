<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\ExpressComponent\Rules\ExpressSwitch;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
use Mollie\WooCommerce\Settings\Settings;
/**
 * Express has no option of its own: it reads each wallet's gateway settings, afresh on every call.
 * A cart or checkout switch never saved reads as ExpressDefaults says.
 */
class ExpressFactsBuilder
{
    private const GATEWAY_PREFIX = 'mollie_wc_gateway_';
    /**
     * @param array{wallets: array<string, array{gatewayId: string, mollieMethod: string, paidAs?: string, paidWallet?: string, checkoutSetting: string, cartSetting: string, addressFrom: string}>, surfaces: array<int, string>, allowedModes: array<int, string>} $config
     * @param array<string, mixed> $paymentMethods Keyed by Mollie method id.
     * @param callable(): array<int, string> $activeMollieMethods Cached method ids active on the profile.
     */
    public function __construct(private array $config, private Settings $settings, private array $paymentMethods, private $activeMollieMethods)
    {
    }
    public function settings(): ExpressSettings
    {
        return new ExpressSettings(supportedSurfaces: array_values($this->config['surfaces']), allowedModes: array_values($this->config['allowedModes']), wallets: $this->config['wallets']);
    }
    public function shopFacts(): ShopFacts
    {
        [$registered, $enabled, $expressOnCheckout, $surcharged, $expressOnCart] = $this->merchantSettings();
        return new ShopFacts(mode: $this->mode(), isHttps: wc_site_is_https(), registeredGatewayIds: $registered, enabledGatewayIds: $enabled, activeMollieMethods: $this->activeMollieMethods(), expressCheckoutGatewayIds: $expressOnCheckout, surchargedGatewayIds: $surcharged, expressCartGatewayIds: $expressOnCart);
    }
    /**
     * @return 'test'|'live' The mode whose key asks Mollie.
     */
    public function mode(): string
    {
        return $this->settings->isTestModeEnabled() ? 'test' : 'live';
    }
    /**
     * The mode and the profile stored when its key was saved; options only, no Mollie call.
     */
    public function account(): string
    {
        return $this->mode() . ':' . (string) get_option($this->settings->getSettingId('profile_merchant_id'), '');
    }
    /** Options only, no Mollie call: cheap enough to run on every request. */
    public function anyWalletTurnedOn(): bool
    {
        [, $enabled, $expressOnCheckout, $surcharged, $expressOnCart] = $this->merchantSettings();
        $expressOnAnyPage = array_merge($expressOnCheckout, $expressOnCart);
        return array_diff(array_intersect($enabled, $expressOnAnyPage), $surcharged) !== [];
    }
    /**
     * Registered, enabled, express-on-checkout, surcharged and express-on-cart gateway ids.
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>, 3: list<string>, 4: list<string>}
     */
    private function merchantSettings(): array
    {
        $registered = [];
        foreach (array_keys($this->paymentMethods) as $methodId) {
            $registered[] = self::GATEWAY_PREFIX . $methodId;
        }
        $enabled = [];
        $expressOnCheckout = [];
        $expressOnCart = [];
        $surcharged = [];
        foreach ($this->config['wallets'] as $row) {
            $gatewayId = $row['gatewayId'];
            if (!in_array($gatewayId, $registered, \true)) {
                continue;
            }
            $settingsOption = $gatewayId . '_settings';
            $stored = (array) get_option($settingsOption, []);
            if (mollieWooCommerceIsGatewayEnabled($settingsOption, 'enabled')) {
                $enabled[] = $gatewayId;
            }
            $onByDefault = \Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressDefaults::onByDefault($gatewayId);
            if (ExpressSwitch::effective($this->storedSwitch($stored, $row['checkoutSetting']), $onByDefault)) {
                $expressOnCheckout[] = $gatewayId;
            }
            if (ExpressSwitch::effective($this->storedSwitch($stored, $row['cartSetting']), $onByDefault)) {
                $expressOnCart[] = $gatewayId;
            }
            $surcharge = $stored['payment_surcharge'] ?? '';
            if ($surcharge !== '' && $surcharge !== Surcharge::NO_FEE) {
                $surcharged[] = $gatewayId;
            }
        }
        return [$registered, $enabled, $expressOnCheckout, $surcharged, $expressOnCart];
    }
    /**
     * The saved value of a switch, null when it was never saved.
     *
     * @param array<array-key, mixed> $stored
     */
    private function storedSwitch(array $stored, string $key): ?string
    {
        $value = $stored[$key] ?? null;
        return is_scalar($value) ? (string) $value : null;
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
