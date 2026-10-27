<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Settings;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;

/**
 * What a merchant saves on a payment method's settings form reaches the shopper: the form is
 * processed by the gateway's own save handler, as WooCommerce calls it on "Save changes", and the
 * method is read back the way the classic checkout and the block checkout read it (both take the
 * gateway's title, description and icon; lib/payment-gateway/src/PaymentGatewayBlocks.php).
 *
 * The e2e suite drives the same form in a browser: tests/qa/tests/03-plugin-settings/mollie-settings-gateway.spec.ts
 * (title shown at both checkouts, logo hidden). The logo upload itself stays there: it is a file upload.
 *
 * @group integration
 * @group Settings
 */
class GatewaySettingsFormTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const METHOD = 'ideal';
    private const GATEWAY = 'mollie_wc_gateway_ideal';
    private const FIELD = 'woocommerce_mollie_wc_gateway_ideal_';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        // Registers the option for restore: the save below writes it directly.
        $this->setGatewaySettingsForTest(self::METHOD, []);
        $this->boot();
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @scenario the merchant renames a method, writes its description and hides its logo
     *   Given the method's settings form
     *   When the merchant saves a title, a description, "display logo" off and a fixed fee of 7
     *   Then the stored settings hold exactly those values
     *   And the method offered at the checkout carries the new title and description, and no icon
     *
     * @test
     * @covers \Inpsyde\PaymentGateway\PaymentGateway::process_admin_options
     */
    public function it_shows_the_shopper_what_the_merchant_saved_on_the_form(): void
    {
        // Given
        $gateway = $this->gateway();

        // When
        $gateway->set_post_data($this->form([
            'enabled' => '1',
            'title' => 'Bank transfer by iDEAL (saved)',
            'description' => 'Settles within the hour.',
            'payment_surcharge' => 'fixed_fee',
            'fixed_fee' => '7',
        ]));
        $this->assertTrue($gateway->process_admin_options());

        // Then
        $settings = get_option(self::GATEWAY . '_settings');
        $this->assertSame('yes', $settings['enabled']);
        $this->assertSame('Bank transfer by iDEAL (saved)', $settings['title']);
        $this->assertSame('Settles within the hour.', $settings['description']);
        $this->assertSame('no', $settings['display_logo']);
        $this->assertSame('fixed_fee', $settings['payment_surcharge']);
        $this->assertSame('7', $settings['fixed_fee']);

        $offered = $this->offeredAfterTheSave();
        $this->assertArrayHasKey(self::GATEWAY, $offered);
        $this->assertSame('Bank transfer by iDEAL (saved)', $offered[self::GATEWAY]->get_title());
        $this->assertStringContainsString('Settles within the hour.', $offered[self::GATEWAY]->get_description());
        $this->assertSame('', $offered[self::GATEWAY]->get_icon(), 'With "display logo" off the method shows no icon.');
    }

    /**
     * @scenario the merchant shows the logo again and switches the method off
     *   Given the method's settings form
     *   When the merchant saves "display logo" on and "enabled" off
     *   Then the method carries an icon and is no longer offered at the checkout
     *
     * @test
     * @covers \Inpsyde\PaymentGateway\PaymentGateway::process_admin_options
     */
    public function it_withdraws_a_method_the_merchant_switched_off(): void
    {
        // Given
        $gateway = $this->gateway();

        // When
        $gateway->set_post_data($this->form(['display_logo' => '1', 'title' => 'iDEAL']));
        $this->assertTrue($gateway->process_admin_options());

        // Then
        $settings = get_option(self::GATEWAY . '_settings');
        $this->assertSame('no', $settings['enabled']);
        $this->assertSame('yes', $settings['display_logo']);
        $this->assertStringContainsString('<img', WC()->payment_gateways()->payment_gateways()[self::GATEWAY]->get_icon());
        $this->assertArrayNotHasKey(self::GATEWAY, $this->offeredAfterTheSave());
    }

    /**
     * The form as the browser posts it: every field prefixed with the gateway's key; a checkbox
     * left out is "off". Fields not named keep the form's empty value.
     *
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private function form(array $fields): array
    {
        $posted = [];
        foreach ($fields as $name => $value) {
            $posted[self::FIELD . $name] = $value;
        }

        return $posted;
    }

    private function gateway(): \WC_Payment_Gateway
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->assertArrayHasKey(self::GATEWAY, $gateways);

        return $gateways[self::GATEWAY];
    }

    /**
     * The gateways a shopper is offered, read by a request that starts after the save: the
     * gateway objects are rebuilt from the stored settings, as on the next page load.
     *
     * @return array<string, \WC_Payment_Gateway>
     */
    private function offeredAfterTheSave(): array
    {
        $this->container = null;
        $this->boot();
        WC()->payment_gateways()->init();
        $this->actAsGuest();
        $this->cartWith(['simple'], 1);
        $this->fillCheckoutForm(array_merge($this->billing(), ['country' => 'NL']), $this->shipping('NL'));

        return WC()->payment_gateways()->get_available_payment_gateways();
    }
}
