<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\PaymentMethods;

use Mollie\WooCommerce\PaymentMethods\Applepay;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Apple Pay's block-checkout express button and the Express Component's mutual exclusion (REQ-A3, A4, A6).
 *
 * The characterisation test pins today's behaviour before the guard is added: one
 * mollie_apple_pay_button_enabled_express_checkout flag decides the cart block and the checkout
 * block alike. That is a known pre-existing bug (rule:business:express-checkout-blocks-visibility-
 * must-be-page-aware) and deliberately stays: the guard applies on the checkout page only.
 *
 * @covers \Mollie\WooCommerce\PaymentMethods\Applepay::isExpressCheckoutEnabled
 */
class ApplepayTest extends TestCase
{
    private const EXPRESS_FLAG = 'mollie_apple_pay_button_enabled_express_checkout';

    /**
     * inc/utils.php is not loaded in unit tests. The helper is deliberately NOT stubbed in setUp:
     * a when() stub there would answer first and silently shadow every expect() below, so a test
     * asserting the helper is never called, or called once with 'checkout', could not fail.
     * Tests that reach the guard without an expectation of their own call this.
     */
    private function expressOwnsNothing(): void
    {
        when('mollieWooCommerceExpressOwnsSurface')->justReturn(false);
    }

    private function makeSut(array $storedSettings): Applepay
    {
        when('get_option')->justReturn($storedSettings);
        return new Applepay();
    }

    private function stubCurrentPage(bool $isCart, bool $isCheckout): void
    {
        when('is_cart')->justReturn($isCart);
        when('is_checkout')->justReturn($isCheckout);
    }

    /**
     * The checkout setting tells the merchant that express checkout shows the button where it can
     * run, and the classic button otherwise (REQ-A5, AC-6).
     */
    public function test_checkout_setting_says_express_checkout_shows_the_button_where_it_can_run(): void
    {
        when('wc_get_page_id')->justReturn(0);
        when('get_edit_post_link')->justReturn(null);

        $fields = $this->makeSut([])->getFormFields([]);

        $description = $fields[self::EXPRESS_FLAG]['desc'];
        self::assertStringContainsString('Mollie express checkout shows the button where it can run', $description);
        self::assertStringContainsString('otherwise the classic Apple Pay button is shown', $description);
    }

    /**
     * Scenario: today the express checkout flag alone decides, on every page
     *   Given the Apple Pay express checkout flag stored as yes or no
     *   And Express does not own the checkout
     *   When isExpressCheckoutEnabled() is called on the cart, the checkout or another page
     *   Then it returns the stored flag
     *
     * @dataProvider pagesAndFlags
     */
    public function test_is_express_checkout_enabled_returns_configured_value_on_cart_and_checkout(
        bool $isCart,
        bool $isCheckout,
        string $flag,
        bool $expected
    ): void {

        // Arrange
        $this->stubCurrentPage($isCart, $isCheckout);
        $this->expressOwnsNothing();
        $sut = $this->makeSut([self::EXPRESS_FLAG => $flag]);

        // When
        $enabled = $sut->isExpressCheckoutEnabled();

        // Then
        self::assertSame($expected, $enabled);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: string, 3: bool}>
     */
    public function pagesAndFlags(): array
    {
        return [
            'cart, flag on' => [true, false, 'yes', true],
            'cart, flag off' => [true, false, 'no', false],
            'checkout, flag on' => [false, true, 'yes', true],
            'checkout, flag off' => [false, true, 'no', false],
            'other page, flag on' => [false, false, 'yes', true],
            'other page, flag off' => [false, false, 'no', false],
        ];
    }

    /**
     * Scenario: on the checkout Apple Pay steps aside when Express owns it
     *   Given the Apple Pay express checkout flag is on
     *   And the current page is the checkout
     *   And mollieWooCommerceExpressOwnsSurface('checkout') answers true
     *   When isExpressCheckoutEnabled() is called
     *   Then it returns false
     *   And the decision was asked of the shared helper, for the checkout, once
     */
    public function test_is_express_checkout_enabled_is_false_on_checkout_when_express_owns_it(): void
    {
        // Arrange
        $this->stubCurrentPage(false, true);
        expect('mollieWooCommerceExpressOwnsSurface')->once()->with('checkout')->andReturn(true);
        $sut = $this->makeSut([self::EXPRESS_FLAG => 'yes']);

        // When
        $enabled = $sut->isExpressCheckoutEnabled();

        // Then
        self::assertFalse($enabled);
    }

    /**
     * Scenario: off the checkout page Express is never consulted and the flag decides
     *   Given the Apple Pay express checkout flag is on
     *   And the current page is the cart
     *   When isExpressCheckoutEnabled() is called
     *   Then it returns true, as today
     *   And mollieWooCommerceExpressOwnsSurface() is not called
     */
    public function test_is_express_checkout_enabled_ignores_express_off_the_checkout_page(): void
    {
        // Arrange
        $this->stubCurrentPage(true, false);
        expect('mollieWooCommerceExpressOwnsSurface')->never();
        $sut = $this->makeSut([self::EXPRESS_FLAG => 'yes']);

        // When
        $enabled = $sut->isExpressCheckoutEnabled();

        // Then
        self::assertTrue($enabled);
    }
}
