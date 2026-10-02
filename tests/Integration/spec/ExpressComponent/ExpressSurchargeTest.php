<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;

/**
 * Gateway surcharges next to the Express Component: the fee a payment method adds to the cart when
 * it is the one chosen in the checkout form, and what the express session is priced at meanwhile.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressSurcharge
 */
class ExpressSurchargeTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const IDEAL = 'mollie_wc_gateway_ideal';

    private const PAYPAL = 'mollie_wc_gateway_paypal';

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
     * Scenario: a surcharge is still added for the method chosen in the checkout form
     *   Given the express buttons are on, and iDEAL carries a fixed surcharge of 2.00
     *   When the shopper chooses iDEAL in the form, and then a method without a surcharge
     *   Then the cart carries the 2.00 fee while iDEAL is chosen, and no fee afterwards
     *
     * @test
     */
    public function it_still_adds_the_surcharge_of_the_method_chosen_in_the_form(): void
    {
        $this->checkoutWithSurcharges(['ideal' => '2.00']);
        $withoutFee = $this->cartTotal();

        $this->chooseInTheForm(self::IDEAL);
        $this->assertSame(['2.00'], $this->cartFees());
        $this->assertGreaterThan((float) $withoutFee, (float) $this->cartTotal());

        $this->chooseInTheForm('cod');
        $this->assertSame([], $this->cartFees());
        $this->assertSame($withoutFee, $this->cartTotal());
    }

    /**
     * Scenario: the express session is priced without the surcharge of the method chosen in the form
     *   Given iDEAL carries a fixed surcharge of 2.00 and is the method chosen in the form
     *   When the checkout asks for an express session and the shopper pays with a wallet
     *   Then the session amount is the cart total without iDEAL's fee, and has no surcharge line
     *   And the express order carries no fee
     *   And the form still has iDEAL chosen, with its fee in the cart
     *
     * @test
     */
    public function it_prices_the_express_session_without_the_surcharge_of_the_method_chosen_in_the_form(): void
    {
        $this->checkoutWithSurcharges(['ideal' => '2.00']);
        $withoutFee = $this->cartTotal();
        $this->chooseInTheForm(self::IDEAL);

        $session = $this->startedSession();
        $payload = $this->sessionPayloads()[0];

        $this->assertSame($withoutFee, $payload['amount']['value'] ?? null, 'The wallet shopper was charged the surcharge of another payment method.');
        $this->assertSame([], $this->surchargeLines($payload));
        $this->assertAnsweredOk($this->startOrder());
        $this->assertSame([], $this->onlyOrderFor($session['ref'])->get_fees());

        $this->assertSame(self::IDEAL, WC()->session->get('chosen_payment_method'), 'The form must keep the method the shopper chose.');
        $this->recalculate();
        $this->assertSame(['2.00'], $this->cartFees(), 'Paying through the form with iDEAL still costs its surcharge.');
    }

    /**
     * Scenario: a wallet whose payment method carries a surcharge is not offered in express
     *   Given PayPal, the only express wallet, carries a fixed surcharge of 1.50
     *   When the checkout asks for an express session
     *   Then the request is refused because no wallet is visible, and nothing is sent to Mollie
     *   And Express does not own the checkout, so PayPal's own express button stays
     *   When the merchant removes the surcharge
     *   Then the session is started
     *
     * One session has one amount for every wallet in it, so it cannot carry a fee that belongs to one.
     *
     * @test
     */
    public function it_does_not_offer_a_wallet_whose_payment_method_carries_a_surcharge(): void
    {
        $this->checkoutWithSurcharges(['paypal' => '1.50']);

        $refused = $this->startSession();

        $this->assertSame(409, $refused->get_status());
        $this->assertSame('no_wallet_visible', $this->loggedEvents('express.session.refused')[0]['context']['reason'] ?? null);
        $this->assertSame([], $this->fakeMollie()->requests('POST', 'sessions'));
        $this->assertFalse(mollieWooCommerceExpressOwnsSurface('checkout'));

        $this->setGatewaySettingsForTest('paypal', ['payment_surcharge' => 'no_fee']);
        $this->token($this->startSession());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * A guest checkout ready for express, with fixed surcharges on the given Mollie methods.
     *
     * @param array<string, string> $fixedFees Mollie method id => fee.
     */
    private function checkoutWithSurcharges(array $fixedFees): void
    {
        foreach ($fixedFees as $method => $fee) {
            $this->setGatewaySettingsForTest($method, ['payment_surcharge' => 'fixed_fee', 'fixed_fee' => $fee]);
        }
        // The surcharge listener is attached on init, so this boot owns both hooks.
        $this->bootExpressOwning(['init', 'woocommerce_cart_calculate_fees']);
        do_action('init');
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
    }

    private function chooseInTheForm(string $gatewayId): void
    {
        WC()->session->set('chosen_payment_method', $gatewayId);
        $this->recalculate();
    }

    /**
     * @return array<int, string> The cart's fee amounts, VAT excluded.
     */
    private function cartFees(): array
    {
        return array_values(array_map(function ($fee): string {
            return $this->decimal((float) $fee->amount);
        }, WC()->cart->get_fees()));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function surchargeLines(array $payload): array
    {
        return array_values(array_filter((array) ($payload['lines'] ?? []), static function ($line): bool {
            return is_array($line) && ($line['type'] ?? '') === 'surcharge';
        }));
    }
}
