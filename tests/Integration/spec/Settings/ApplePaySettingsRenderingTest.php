<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Settings;

use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;

/**
 * The Apple Pay settings form, from the gateway's fields to the HTML the merchant sees.
 *
 * The unit test reads the field array, so it cannot see which keys WooCommerce renders: a text under
 * a key WC_Settings_API ignores passes there and is missing on the page. This test renders the
 * real gateway form.
 *
 * @group integration
 * @group ApplePaySettings
 */
class ApplePaySettingsRenderingTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const GATEWAY = 'mollie_wc_gateway_applepay';

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
     * Scenario: each Apple Pay button setting explains itself on the page
     *   Given the Apple Pay gateway is registered
     *   When WooCommerce renders its settings form
     *   Then the cart, product and checkout checkboxes each show their text under the checkbox
     *   And the checkout text says Mollie express checkout shows the button where it can run
     *
     * @test
     * @dataProvider buttonSettings
     */
    public function it_shows_the_description_of_each_button_setting(string $fieldKey, string $text): void
    {
        $html = $this->renderedSettingsForm();

        $this->assertRegExp(
            '~<p class="description">\s*' . preg_quote($text, '~') . '\s*</p>~',
            $this->sectionOf($html, $fieldKey)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function buttonSettings(): array
    {
        return [
            'cart' => [
                'mollie_apple_pay_button_enabled_cart',
                'Enable the Apple Pay direct buy button on the Cart page',
            ],
            'product' => [
                'mollie_apple_pay_button_enabled_product',
                'Enable the Apple Pay direct buy button on the Product page',
            ],
            'checkout' => [
                'mollie_apple_pay_button_enabled_express_checkout',
                'Enable the Apple Pay direct buy button on the Express Buttons section of the Checkout page. On the block checkout, Mollie express checkout shows the button where it can run; otherwise the classic Apple Pay button is shown.',
            ],
        ];
    }

    private function renderedSettingsForm(): string
    {
        $this->bootExpress();
        $gateway = WC()->payment_gateways()->payment_gateways()[self::GATEWAY] ?? null;
        $this->assertInstanceOf(\WC_Payment_Gateway::class, $gateway, 'Apple Pay must be a registered gateway.');

        return $gateway->generate_settings_html($gateway->get_form_fields(), false);
    }

    /**
     * The table row of one field, so a text of the next field cannot satisfy this one.
     */
    private function sectionOf(string $html, string $fieldKey): string
    {
        $input = 'id="woocommerce_' . self::GATEWAY . '_' . $fieldKey . '"';
        $start = strpos($html, $input);
        $this->assertNotFalse($start, "The form must render the field $fieldKey.");
        $end = strpos($html, '</tr>', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }
}
