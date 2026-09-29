<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Psr\Container\ContainerInterface;

/**
 * The Google Pay payment method, which exists only for the Express Component.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressGooglePay
 */
class ExpressGooglePayTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const GATEWAY = 'mollie_wc_gateway_googlepay';

    private const DEFAULT_METHODS = ['ideal', 'creditcard', 'banktransfer', 'paypal', 'applepay'];

    private ?ContainerInterface $bootedContainer = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpExpressCheckout();
    }

    public function tearDown(): void
    {
        $this->tearDownExpressCheckout();
        parent::tearDown();
    }

    /**
     * Scenario: Google Pay registers only for a profile where Mollie reports it activated
     *   Given Mollie's methods list with or without googlepay
     *   When the plugin boots
     *   Then mollie_wc_gateway_googlepay is registered, and listed on the admin tab, exactly when googlepay is in that list
     *
     * @test
     * @dataProvider activationAtMollie
     */
    public function it_registers_google_pay_only_when_mollie_reports_it_activated(bool $activated): void
    {
        $this->googlePayAtMollie($activated);

        $container = $this->bootExpressOwning(['woocommerce_payment_gateways']);

        $this->assertSame($activated, array_key_exists(self::GATEWAY, WC()->payment_gateways()->payment_gateways()));
        $this->assertSame($activated, array_key_exists('googlepay', $container->get('gateway.paymentMethods')));
        $this->assertArrayHasKey(
            'mollie_wc_gateway_paypal',
            WC()->payment_gateways()->payment_gateways(),
            'The other methods must register as before.'
        );
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function activationAtMollie(): array
    {
        return [
            'activated at Mollie' => [true],
            'not in the methods list' => [false],
        ];
    }

    /**
     * Scenario: the settings page shows one express checkbox, off on a fresh install
     *   Given Google Pay is activated at Mollie
     *   And the shop has never saved Google Pay settings
     *   When the plugin boots
     *   Then the settings form has one field, the enabled checkbox, labelled for express checkout
     *   And it is off, although the general enabled default is on
     *
     * @test
     */
    public function it_shows_one_express_checkbox_off_by_default(): void
    {
        $this->googlePayAtMollie(true);
        $this->setOptionForTest(self::GATEWAY . '_settings', []);
        delete_option(self::GATEWAY . '_settings');

        $this->bootExpress();

        $gateway = WC()->payment_gateways()->payment_gateways()[self::GATEWAY] ?? null;
        $this->assertInstanceOf(\WC_Payment_Gateway::class, $gateway, 'Google Pay must be a registered gateway.');
        $fields = $gateway->get_form_fields();
        $this->assertSame(['enabled'], array_keys($fields));
        $this->assertSame('checkbox', $fields['enabled']['type']);
        $this->assertSame('Show Google Pay in express checkout on the checkout page', $fields['enabled']['label']);
        $this->assertSame('no', $fields['enabled']['default']);
        $this->assertSame('no', $gateway->get_option('enabled'));
    }

    /**
     * Scenario: Google Pay is never offered as a payment method of its own
     *   Given Google Pay activated at Mollie and switched on by the merchant
     *   And a guest with a cart ready on the checkout
     *   When WooCommerce lists the payment methods the shopper can choose
     *   Then Google Pay is not among them, while PayPal is
     *   And it is not registered for the block checkout, while Apple Pay is
     *
     * @test
     */
    public function it_never_offers_google_pay_as_a_checkout_payment_method(): void
    {
        $this->googlePayAtMollie(true);
        $this->setGatewaySettingsForTest('googlepay', ['enabled' => 'yes']);

        $this->readyGuestCheckout();
        $container = $this->container();

        $this->assertArrayHasKey(self::GATEWAY, WC()->payment_gateways()->payment_gateways(), 'Google Pay must be a registered gateway.');
        $this->assertSame('yes', WC()->payment_gateways()->payment_gateways()[self::GATEWAY]->enabled);
        $available = WC()->payment_gateways()->get_available_payment_gateways();
        $this->assertArrayNotHasKey(self::GATEWAY, $available);
        $this->assertArrayHasKey('mollie_wc_gateway_paypal', $available, 'The shop must have offered methods, or the check proves nothing.');
        $this->assertFalse($container->get('payment_gateway.' . self::GATEWAY . '.register_blocks'));
        $this->assertTrue($container->get('payment_gateway.mollie_wc_gateway_applepay.register_blocks'));
    }

    /**
     * Keeps the container, which readyGuestCheckout() does not return.
     *
     * @param array<string, callable> $serviceOverrides
     */
    protected function bootExpress(array $serviceOverrides = []): ContainerInterface
    {
        $this->bootedContainer = parent::bootExpress($serviceOverrides);

        return $this->bootedContainer;
    }

    private function container(): ContainerInterface
    {
        $this->assertNotNull($this->bootedContainer, 'The plugin must be booted first.');

        return $this->bootedContainer;
    }

    private function googlePayAtMollie(bool $activated): void
    {
        $this->fakeMollie()->setMethods($activated ? array_merge(self::DEFAULT_METHODS, ['googlepay']) : self::DEFAULT_METHODS);
        $this->flushMollieMethodsCache();
    }
}
