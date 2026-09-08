<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\PaymentMethods;

use Mollie\WooCommerce\PaymentMethods\Paypal;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\PaymentMethods\Paypal::getFormFields
 * @covers \Mollie\WooCommerce\PaymentMethods\Paypal::isExpressCheckoutEnabled
 */
class PaypalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // buttonOptions() runs unconditionally inside getFormFields() and calls _x() for every color option.
        when('_x')->returnArg(1);
    }

    private function makeSut(array $storedSettings): Paypal
    {
        when('get_option')->justReturn($storedSettings);
        return new Paypal();
    }

    private function stubCurrentPage(bool $isCart, bool $isCheckout): void
    {
        when('is_cart')->justReturn($isCart);
        when('is_checkout')->justReturn($isCheckout);
    }

    /** @scenario Paypal::getFormFields() includes a 'mollie_paypal_button_enabled_checkout' field of type 'checkbox' with default 'no', distinct from 'mollie_paypal_button_enabled_cart'. */
    public function test_form_fields_include_dedicated_checkout_checkbox(): void
    {
        // Arrange
        $sut = $this->makeSut([]);

        // When
        $fields = $sut->getFormFields([]);

        // Then
        self::assertArrayHasKey('mollie_paypal_button_enabled_checkout', $fields);
        self::assertSame('checkbox', $fields['mollie_paypal_button_enabled_checkout']['type']);
        self::assertSame('no', $fields['mollie_paypal_button_enabled_checkout']['default']);
        self::assertArrayHasKey('mollie_paypal_button_enabled_cart', $fields);
        self::assertNotSame(
            $fields['mollie_paypal_button_enabled_cart'],
            $fields['mollie_paypal_button_enabled_checkout']
        );
    }

    /** @scenario On the Cart page, isExpressCheckoutEnabled() reflects only mollie_paypal_button_enabled_cart. */
    public function test_is_express_checkout_enabled_on_cart_page_reads_cart_setting(): void
    {
        // Arrange
        $this->stubCurrentPage(true, false);

        // When / Then — cart setting enabled
        $sutEnabled = $this->makeSut(['mollie_paypal_button_enabled_cart' => 'yes']);
        self::assertTrue($sutEnabled->isExpressCheckoutEnabled());

        // When / Then — cart setting disabled
        $sutDisabled = $this->makeSut(['mollie_paypal_button_enabled_cart' => 'no']);
        self::assertFalse($sutDisabled->isExpressCheckoutEnabled());
    }

    /** @scenario On the Checkout page, isExpressCheckoutEnabled() reflects only mollie_paypal_button_enabled_checkout. */
    public function test_is_express_checkout_enabled_on_checkout_page_reads_checkout_setting(): void
    {
        // Arrange
        $this->stubCurrentPage(false, true);

        // When / Then — checkout setting enabled
        $sutEnabled = $this->makeSut(['mollie_paypal_button_enabled_checkout' => 'yes']);
        self::assertTrue($sutEnabled->isExpressCheckoutEnabled());

        // When / Then — checkout setting disabled
        $sutDisabled = $this->makeSut(['mollie_paypal_button_enabled_checkout' => 'no']);
        self::assertFalse($sutDisabled->isExpressCheckoutEnabled());
    }

    /** @scenario Enabling one page's setting must not enable the express button on the other page — the cart and checkout settings are fully independent. */
    public function test_is_express_checkout_enabled_settings_do_not_leak_across_pages(): void
    {
        // Cart page: checkout is enabled but cart is not — must stay hidden on cart
        $this->stubCurrentPage(true, false);
        $sutOnCart = $this->makeSut([
            'mollie_paypal_button_enabled_cart' => 'no',
            'mollie_paypal_button_enabled_checkout' => 'yes',
        ]);
        self::assertFalse($sutOnCart->isExpressCheckoutEnabled());

        // Checkout page: cart is enabled but checkout is not — must stay hidden on checkout
        $this->stubCurrentPage(false, true);
        $sutOnCheckout = $this->makeSut([
            'mollie_paypal_button_enabled_cart' => 'yes',
            'mollie_paypal_button_enabled_checkout' => 'no',
        ]);
        self::assertFalse($sutOnCheckout->isExpressCheckoutEnabled());
    }

    /** @scenario Outside the Cart and Checkout pages, isExpressCheckoutEnabled() is always false regardless of stored settings. */
    public function test_is_express_checkout_enabled_false_outside_cart_and_checkout_pages(): void
    {
        // Arrange
        $this->stubCurrentPage(false, false);

        // When
        $sut = $this->makeSut([
            'mollie_paypal_button_enabled_cart' => 'yes',
            'mollie_paypal_button_enabled_checkout' => 'yes',
        ]);

        // Then
        self::assertFalse($sut->isExpressCheckoutEnabled());
    }
}