<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Gateway;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;

/**
 * Which Mollie methods the checkout offers. Mollie decides per amount, currency, billing country
 * and kind of payment; the plugin has to ask about this cart and this shopper, and then apply the
 * merchant's own settings.
 *
 * The e2e suite covers it in tests/qa/tests/04-frontend-ui, 07-subscription/subscription-ui and
 * the "selling only to specific countries" cases of 03-plugin-settings;
 * tests/Integration/e2e-equivalence.md has the list.
 *
 * @group integration
 * @group CheckoutAvailability
 */
class CheckoutAvailabilityTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
    }

    public function tearDown(): void
    {
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: the checkout asks Mollie about this cart and this shopper
     *   Given a cart and a shopper with a billing country
     *   When the payment methods of the checkout are worked out
     *   Then Mollie is asked for the cart total, the shop currency and that billing country
     *
     * @test
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::is_available
     */
    public function it_asks_mollie_about_the_cart_total_currency_and_billing_country(): void
    {
        $this->shopperAtTheCheckout('DE', ['simple'], 2);

        $this->offeredMollieMethods();

        $asked = array_values(array_filter($this->fakeMollie()->requests('GET', 'methods'), static function (array $request): bool {
            return $request['path'] === 'methods' && isset($request['query']['amount']);
        }));
        $this->assertNotSame([], $asked, 'Mollie was asked which methods it offers for an amount.');
        $this->assertSame(number_format((float) WC()->cart->get_total('edit'), 2, '.', ''), $asked[0]['query']['amount']['value']);
        $this->assertSame('EUR', $asked[0]['query']['amount']['currency']);
        $this->assertSame('DE', $asked[0]['query']['billingCountry']);
    }

    /**
     * Scenario: a method Mollie does not offer for this cart is not offered at the checkout
     *   Given Mollie offers a method only up to an amount, only in a currency, or only in a country
     *   When the cart or the shopper is outside of that
     *   Then the checkout does not offer that method, and still offers the others
     *   And inside of it, the checkout offers it
     *
     * @test
     * @dataProvider mollieRules
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::is_available
     *
     * @param array<string, mixed> $rule
     */
    public function it_offers_a_method_only_when_mollie_offers_it_for_this_cart(array $rule, string $country, string $currency, bool $offered): void
    {
        $this->fakeMollie()->setMethods(['ideal', 'creditcard']);
        $this->fakeMollie()->offerMethodOnlyFor('ideal', $rule);
        $this->addTestFilter('woocommerce_currency', static function () use ($currency): string {
            return $currency;
        });
        $this->shopperAtTheCheckout($country, ['simple'], 2);

        $methods = $this->offeredMollieMethods();

        $this->assertSame($offered, in_array('ideal', $methods, true));
        $this->assertContains('creditcard', $methods);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string, 3: bool}>
     */
    public function mollieRules(): array
    {
        return [
            'the cart is above the maximum' => [['max' => '15.00'], 'DE', 'EUR', false],
            'the cart is below the maximum' => [['max' => '500.00'], 'DE', 'EUR', true],
            'the cart is below the minimum' => [['min' => '500.00'], 'DE', 'EUR', false],
            'another currency' => [['currencies' => ['EUR']], 'DE', 'CHF', false],
            'the currency of the method' => [['currencies' => ['CHF']], 'DE', 'CHF', true],
            'another billing country' => [['countries' => ['NL']], 'DE', 'EUR', false],
            'the billing country of the method' => [['countries' => ['NL']], 'NL', 'EUR', true],
        ];
    }

    /**
     * Scenario: the merchant sells a method only to some countries
     *   Given Mollie offers the method everywhere
     *   And the merchant restricted it to Spain in the method's settings
     *   When a shopper from Germany, then one from Spain, reaches the checkout
     *   Then only the shopper from Spain is offered it
     *
     * @test
     * @dataProvider shopperCountries
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::is_available
     */
    public function it_offers_a_method_only_to_the_countries_the_merchant_allows(string $country, bool $offered): void
    {
        $this->setGatewaySettingsForTest('ideal', ['allowed_countries' => ['ES']]);
        $this->shopperAtTheCheckout($country, ['simple'], 2);

        $methods = $this->offeredMollieMethods();

        $this->assertSame($offered, in_array('ideal', $methods, true));
        $this->assertContains('creditcard', $methods, 'A method without the restriction is still offered.');
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function shopperCountries(): array
    {
        return [
            'C420329 a shopper from Germany' => ['DE', false],
            'a shopper from Spain' => ['ES', true],
        ];
    }

    /**
     * Scenario: a method the merchant switched off is not offered
     *   Given Mollie offers the method
     *   And the merchant disabled it in WooCommerce
     *   Then the checkout does not offer it
     *
     * @test
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::is_available
     */
    public function it_does_not_offer_a_method_the_merchant_switched_off(): void
    {
        $this->setGatewaySettingsForTest('ideal', ['enabled' => 'no']);
        $this->shopperAtTheCheckout('DE', ['simple'], 2);

        $methods = $this->offeredMollieMethods();

        $this->assertNotContains('ideal', $methods);
        $this->assertContains('creditcard', $methods);
    }

    /**
     * Scenario: direct debit is never offered at the checkout
     *   Given direct debit is active at Mollie, where it pays the renewals of other methods
     *   Then the checkout does not offer it
     *
     * @test
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::is_available
     */
    public function it_never_offers_direct_debit_at_the_checkout(): void
    {
        $this->fakeMollie()->setMethods(['ideal', 'creditcard', 'directdebit']);
        $this->shopperAtTheCheckout('DE', ['simple'], 2);

        $this->assertNotContains('directdebit', $this->offeredMollieMethods());
    }

    /**
     * Scenario: a subscription in the cart leaves only the methods that can start one
     *   Given every method below is active at Mollie
     *   When the cart holds a subscription product
     *   Then the checkout offers the methods that can make a first payment with a mandate, and no other
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieSubscription::isAvailableForSubscriptions
     */
    public function it_offers_only_the_methods_that_can_start_a_subscription(): void
    {
        if (!class_exists(\WC_Subscriptions::class)) {
            $this->markTestSkipped('WooCommerce Subscriptions is not active.');
        }
        // The e2e case C3348 names the first seven. PayPal is on the plugin's list too; Mollie
        // offers it for a first payment only to accounts with reference transactions, which the
        // next test covers from the other side.
        $canStartASubscription = ['ideal', 'creditcard', 'bancontact', 'eps', 'kbc', 'belfius', 'paybybank', 'paypal'];
        $cannot = ['banktransfer', 'klarna', 'przelewy24', 'directdebit'];
        $this->fakeMollie()->setMethods(array_merge($canStartASubscription, $cannot));
        $this->shopperAtTheCheckout('DE', ['subscription'], 1);

        $methods = $this->offeredMollieMethods();

        sort($methods);
        sort($canStartASubscription);
        $this->assertSame($canStartASubscription, $methods);
    }

    /**
     * Scenario: a method Mollie does not offer for a first payment is not offered for a subscription
     *   Given Mollie offers card payments for one-off payments only
     *   When the cart holds a subscription product
     *   Then the checkout does not offer the card
     *   And for an ordinary product it does
     *
     * @test
     * @dataProvider carts
     * @covers \Mollie\WooCommerce\Payment\MollieSubscription::isAvailableForSubscriptions
     */
    public function it_asks_mollie_whether_a_method_can_take_the_first_payment_of_a_subscription(string $product, bool $offered): void
    {
        if (!class_exists(\WC_Subscriptions::class)) {
            $this->markTestSkipped('WooCommerce Subscriptions is not active.');
        }
        $this->fakeMollie()->setMethods(['ideal', 'creditcard']);
        $this->fakeMollie()->offerMethodOnlyFor('creditcard', ['sequenceTypes' => ['oneoff']]);
        $this->shopperAtTheCheckout('DE', [$product], 1);

        $this->assertSame($offered, in_array('creditcard', $this->offeredMollieMethods(), true));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function carts(): array
    {
        return [
            'a subscription' => ['subscription', false],
            'an ordinary product' => ['simple', true],
        ];
    }

    /**
     * @param array<int, string> $products
     */
    private function shopperAtTheCheckout(string $country, array $products, int $quantity): void
    {
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith($products, $quantity);
        $billing = array_merge($this->billing(), ['country' => $country]);
        $this->fillCheckoutForm($billing, $this->shipping($country));
        WC()->cart->calculate_totals();
        // What Mollie offers is cached per question; nothing asked before this shopper counts.
        $this->flushMollieMethodsCache();
    }

    /**
     * The Mollie method ids among the gateways WooCommerce offers this shopper.
     *
     * @return array<int, string>
     */
    private function offeredMollieMethods(): array
    {
        $ids = [];
        foreach (array_keys(WC()->payment_gateways()->get_available_payment_gateways()) as $gatewayId) {
            if (strpos((string) $gatewayId, 'mollie_wc_gateway_') === 0) {
                $ids[] = substr((string) $gatewayId, strlen('mollie_wc_gateway_'));
            }
        }

        return $ids;
    }
}
