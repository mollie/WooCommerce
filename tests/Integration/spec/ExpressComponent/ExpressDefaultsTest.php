<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\Activation\ActivationModule;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Psr\Container\ContainerInterface;

/**
 * What the cart and checkout express switches read before the merchant saves them: on for a new
 * install, as before for an existing one, and on for a Google Pay an existing install never set up.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressDefaults
 */
class ExpressDefaultsTest extends ExpressFlowTestCase
{
    private const DEFAULTS_OPTION = 'mollie_express_default_on';

    private const METHODS = ['ideal', 'creditcard', 'banktransfer', 'paypal', 'applepay', 'googlepay'];

    /**
     * Cart, checkout and product switch of each wallet; Google Pay has no product switch.
     */
    private const SWITCHES = [
        'applepay' => [
            'cart' => 'mollie_apple_pay_button_enabled_cart',
            'checkout' => 'mollie_apple_pay_button_enabled_express_checkout',
            'product' => 'mollie_apple_pay_button_enabled_product',
        ],
        'paypal' => [
            'cart' => 'mollie_paypal_button_enabled_cart',
            'checkout' => 'mollie_paypal_button_enabled_checkout',
            'product' => 'mollie_paypal_button_enabled_product',
        ],
        'googlepay' => [
            'cart' => 'mollie_googlepay_button_enabled_cart',
            'checkout' => 'mollie_googlepay_button_enabled_checkout',
        ],
    ];

    private const ALL_WALLETS = ['mollie_wc_gateway_applepay', 'mollie_wc_gateway_googlepay', 'mollie_wc_gateway_paypal'];

    private ?ContainerInterface $container = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->fakeMollie()->setMethods(self::METHODS);
        $this->flushMollieMethodsCache();
    }

    /**
     * @scenario A fresh install shows the express buttons on the cart and the checkout
     *   Given a site where the plugin has never run: no stored version, no wallet settings
     *   When the plugin first runs and the merchant enables Apple Pay, PayPal and Google Pay
     *   Then the express defaults are recorded as on
     *   And the cart and checkout switches of the three wallets read on
     *   And the product-page switches read off
     *
     * @test
     * @group REQ-NEW-EXPRESS-DEFAULTS
     * @group REQ-A2
     */
    public function it_turns_the_cart_and_checkout_switches_on_at_a_fresh_install(): void
    {
        // Given
        $this->freshSite();

        // When
        $this->runPluginInit();
        $this->enableWallets();

        // Then
        self::assertSame('yes', get_option(self::DEFAULTS_OPTION));
        self::assertSame(self::ALL_WALLETS, $this->walletsOn('cart'));
        self::assertSame(self::ALL_WALLETS, $this->walletsOn('checkout'));
        foreach (['applepay', 'paypal'] as $methodId) {
            self::assertFalse(
                mollieWooCommerceIsGatewayEnabled($this->optionName($methodId), self::SWITCHES[$methodId]['product']),
                "The {$methodId} product-page switch must read off."
            );
        }
    }

    /**
     * @scenario An update keeps the express buttons of a new install on, and keeps what the merchant saved
     *   Given a fresh install with the three wallets enabled
     *   And the PayPal cart switch saved off by the merchant
     *   And the Apple Pay checkout switch missing from its settings, as for a switch added after the install
     *   When the plugin is updated to a newer version and the install is no longer marked new
     *   Then the cart and checkout switches still read on, but the PayPal cart switch stays off
     *
     * @test
     * @group REQ-NEW-EXPRESS-DEFAULTS
     * @group REQ-A2
     */
    public function it_keeps_the_switches_on_after_the_new_install_is_updated(): void
    {
        // Given
        $this->freshSite();
        $this->runPluginInit();
        $this->enableWallets();
        $this->setGatewaySettingsForTest('paypal', [self::SWITCHES['paypal']['cart'] => 'no']);
        $this->removeSwitch('applepay', self::SWITCHES['applepay']['checkout']);

        // When
        $this->setOptionForTest(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '8.2.0');
        $this->runPluginInit();
        $this->setOptionForTest(SharedDataDictionary::NEW_INSTALL_PARAM_NAME, 'no');

        // Then
        self::assertSame(self::ALL_WALLETS, $this->walletsOn('checkout'));
        self::assertSame(['mollie_wc_gateway_applepay', 'mollie_wc_gateway_googlepay'], $this->walletsOn('cart'));
    }

    /**
     * @scenario An existing install that never set up Google Pay shows its buttons once it is enabled
     *   Given an install from before Google Pay had page switches, with Google Pay stored disabled
     *   And the Google Pay switches never saved, or its cart switch saved off
     *   When the plugin is updated and the merchant enables Google Pay
     *   Then each Google Pay switch never saved reads on
     *   And a saved one is unchanged
     *
     * @test
     * @dataProvider googlePayBeforeTheUpdate
     * @param array<string, string> $stored
     * @param array{cart: bool, checkout: bool} $expected
     * @group REQ-A7
     * @group REQ-NEW-EXPRESS-DEFAULTS
     */
    public function it_turns_google_pay_on_when_an_existing_install_enables_it(array $stored, array $expected): void
    {
        // Given
        $this->forgetOption(self::DEFAULTS_OPTION);
        $this->setOptionForTest($this->optionName('googlepay'), $stored);
        $this->setOptionForTest(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '8.2.0');
        $this->container = $this->bootExpress();

        // When
        $this->runPluginInit();
        $this->setGatewaySettingsForTest('googlepay', ['enabled' => 'yes']);

        // Then
        self::assertSame(
            $expected,
            [
                'cart' => in_array('mollie_wc_gateway_googlepay', $this->walletsOn('cart'), true),
                'checkout' => in_array('mollie_wc_gateway_googlepay', $this->walletsOn('checkout'), true),
            ]
        );
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: array{cart: bool, checkout: bool}}>
     */
    public function googlePayBeforeTheUpdate(): array
    {
        return [
            'switches never saved' => [['enabled' => 'no'], ['cart' => true, 'checkout' => true]],
            'cart switch saved off' => [
                ['enabled' => 'no', 'mollie_googlepay_button_enabled_cart' => 'no'],
                ['cart' => false, 'checkout' => true],
            ],
        ];
    }

    /**
     * @scenario The settings page shows each switch's default as what is read when it was never saved
     *   Given a new or an existing install
     *   And a wallet enabled whose cart and checkout switches were never saved
     *   When its settings page is built and the switches are read
     *   Then the cart and checkout defaults are on for a new install, and for Google Pay on an existing one
     *   And off for Apple Pay and PayPal on an existing install
     *   And each default equals what is read
     *   And the product-page switch defaults to off
     *
     * @test
     * @dataProvider walletsByInstall
     * @group REQ-NEW-EXPRESS-DEFAULTS
     * @group REQ-A2
     */
    public function it_defaults_each_switch_form_field_to_what_is_read(string $methodId, bool $newInstall, string $expected): void
    {
        // Given
        if ($newInstall) {
            $this->setOptionForTest(self::DEFAULTS_OPTION, 'yes');
        } else {
            $this->forgetOption(self::DEFAULTS_OPTION);
        }
        $this->setGatewaySettingsForTest($methodId, ['enabled' => 'yes']);
        $this->removeSwitch($methodId, self::SWITCHES[$methodId]['cart']);
        $this->removeSwitch($methodId, self::SWITCHES[$methodId]['checkout']);

        // When
        $this->container = $this->bootExpress();
        $gateway = WC()->payment_gateways()->payment_gateways()['mollie_wc_gateway_' . $methodId] ?? null;
        self::assertInstanceOf(\WC_Payment_Gateway::class, $gateway, "{$methodId} must be a registered gateway.");
        $fields = $gateway->get_form_fields();

        // Then
        foreach (['cart', 'checkout'] as $surface) {
            $key = self::SWITCHES[$methodId][$surface];
            self::assertSame($expected, $fields[$key]['default'] ?? null, "Form default of {$key}.");
            self::assertSame(
                $expected === 'yes',
                in_array('mollie_wc_gateway_' . $methodId, $this->walletsOn($surface), true),
                "What is read for {$key} must equal its form default."
            );
        }
        if (isset(self::SWITCHES[$methodId]['product'])) {
            self::assertSame('no', $fields[self::SWITCHES[$methodId]['product']]['default'] ?? null);
        }
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public function walletsByInstall(): array
    {
        return [
            'Apple Pay, new install' => ['applepay', true, 'yes'],
            'PayPal, new install' => ['paypal', true, 'yes'],
            'Google Pay, new install' => ['googlepay', true, 'yes'],
            'Apple Pay, existing install' => ['applepay', false, 'no'],
            'PayPal, existing install' => ['paypal', false, 'no'],
            'Google Pay, existing install' => ['googlepay', false, 'yes'],
        ];
    }

    /**
     * No stored version, no install flag, no express defaults and no wallet settings, then booted.
     */
    private function freshSite(): void
    {
        foreach ([
            SharedDataDictionary::DB_VERSION_PARAM_NAME,
            SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME,
            SharedDataDictionary::NEW_INSTALL_PARAM_NAME,
            self::DEFAULTS_OPTION,
        ] as $option) {
            $this->forgetOption($option);
        }
        foreach (array_keys(self::SWITCHES) as $methodId) {
            $this->forgetOption($this->optionName($methodId));
        }
        $this->container = $this->bootExpress();
    }

    /**
     * What the plugin does on init, in the order inc/modules.php registers it: the activation module
     * records a new install or an update, then each payment method stores its defaults when it has none.
     */
    private function runPluginInit(): void
    {
        $activation = null;
        foreach ($GLOBALS['wp_filter']['init']->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'];
                if (is_array($function) && $function[0] instanceof ActivationModule && $function[1] === 'pluginInit') {
                    $activation = $function[0];
                }
            }
        }
        self::assertInstanceOf(ActivationModule::class, $activation, 'The activation module must be hooked on init.');

        $activation->pluginInit();
        $container = $this->container();
        foreach ($container->get('gateway.paymentMethods') as $paymentMethod) {
            assert($paymentMethod instanceof PaymentMethodI);
            $paymentMethod->updateSettingsWithDefaults($container);
        }
    }

    private function enableWallets(): void
    {
        foreach (array_keys(self::SWITCHES) as $methodId) {
            $this->setGatewaySettingsForTest($methodId, ['enabled' => 'yes']);
        }
    }

    /**
     * Gateway ids whose express switch for the surface reads on, sorted.
     *
     * @return list<string>
     */
    private function walletsOn(string $surface): array
    {
        $facts = $this->container()->get(ExpressFactsBuilder::class);
        assert($facts instanceof ExpressFactsBuilder);
        $ids = $facts->shopFacts()->expressGatewayIds($surface);
        sort($ids);

        return $ids;
    }

    private function removeSwitch(string $methodId, string $key): void
    {
        $settings = get_option($this->optionName($methodId), []);
        $settings = is_array($settings) ? $settings : [];
        unset($settings[$key]);
        $this->setOptionForTest($this->optionName($methodId), $settings);
    }

    /**
     * Deletes an option for this test; tearDown() restores it.
     */
    private function forgetOption(string $name): void
    {
        $this->setOptionForTest($name, '');
        delete_option($name);
    }

    private function optionName(string $methodId): string
    {
        return 'mollie_wc_gateway_' . $methodId . '_settings';
    }

    private function container(): ContainerInterface
    {
        self::assertNotNull($this->container, 'The plugin must be booted first.');

        return $this->container;
    }
}
