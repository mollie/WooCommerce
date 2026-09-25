<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\PricingFingerprint;
use Mollie\WooCommerce\Core\Express\StartOrderDecision;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\CartLine;
use Mollie\WooCommerce\Core\Types\CartShipping;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\Money;
use Mollie\WooCommerce\Core\Types\Refuse;
use Mollie\WooCommerceTests\TestCase;

/**
 * What an order request at Mollie's submit event gets (REQ-B3, B5, B6, C4; AC-8, AC-10, AC-12, AC-14, AC-14b).
 *
 * Ordered guards, first match wins, so one request never has two answers:
 *   1. no remembered session                                → refuse session_missing
 *   2. the session's expiresAt has been reached             → refuse session_expired
 *   3. the cart ships and the destination or rate is missing → refuse shipping_incomplete
 *   4. the pricing fingerprint differs from the session's   → refuse cart_changed
 *      (covers a changed cart, a changed address and a changed rate)
 *   5. an order already carries this express_ref            → reuse
 *   6. otherwise                                            → create
 *
 * @covers \Mollie\WooCommerce\Core\Express\StartOrderDecision
 */
class StartOrderDecisionTest extends TestCase
{
    private const NOW = 1790000000;

    private const EXPIRES_AT = self::NOW + 600;

    /**
     * Scenario: every reason code and both outcomes have a row
     *   Given the facts of an order request: the remembered session, an order already carrying its ref, the cart
     *   When the decision is asked at a fixed time
     *   Then it refuses with the reason of the first guard that fails
     *   And otherwise it reuses the existing order or creates one
     *
     * @dataProvider requests
     * @covers \Mollie\WooCommerce\Core\Express\StartOrderDecision::decide
     */
    public function testDecidesWhatAnOrderRequestGets(
        bool $hasSession,
        int $expiresAt,
        bool $sameFingerprint,
        ?int $existingOrderId,
        CartFacts $cart,
        string $expected
    ): void {

        $facts = $hasSession
            ? new ExpressOrderFacts(
                sessionId: 'sess_abc',
                expressRef: 'exr_abc',
                fingerprint: $sameFingerprint ? PricingFingerprint::of($cart) : 'fingerprint-of-another-checkout',
                expiresAt: $expiresAt,
                existingOrderId: $existingOrderId
            )
            : new ExpressOrderFacts();

        $decision = StartOrderDecision::decide($facts, $cart, self::NOW);

        if ($decision instanceof Refuse) {
            self::assertSame($expected, 'refuse:' . $decision->code());

            return;
        }
        self::assertSame($expected, $decision);
        self::assertContains($decision, [StartOrderDecision::CREATE, StartOrderDecision::REUSE]);
    }

    /**
     * @return array<string, array{0: bool, 1: int, 2: bool, 3: ?int, 4: CartFacts, 5: string}>
     */
    public function requests(): array
    {
        $shipsReady = $this->cart(true, true, true);
        $shipsNoDestination = $this->cart(true, false, true);
        $shipsNoRate = $this->cart(true, true, false);
        $virtual = $this->cart(false, false, false);

        return [
            // One row per reason code.
            'no session was started' => [false, 0, false, null, $shipsReady, 'refuse:session_missing'],
            'the session expired a second ago' => [true, self::NOW - 1, true, null, $shipsReady, 'refuse:session_expired'],
            'the session expires this very second' => [true, self::NOW, true, null, $shipsReady, 'refuse:session_expired'],
            'ships, destination incomplete' => [true, self::EXPIRES_AT, true, null, $shipsNoDestination, 'refuse:shipping_incomplete'],
            'ships, no rate chosen' => [true, self::EXPIRES_AT, true, null, $shipsNoRate, 'refuse:shipping_incomplete'],
            'the checkout was priced differently' => [true, self::EXPIRES_AT, false, null, $shipsReady, 'refuse:cart_changed'],
            // The two outcomes.
            'an order carries the ref already' => [true, self::EXPIRES_AT, true, 4711, $shipsReady, 'reuse'],
            'everything holds, ships' => [true, self::EXPIRES_AT, true, null, $shipsReady, 'create'],
            'everything holds, nothing to ship, form empty' => [true, self::EXPIRES_AT, true, null, $virtual, 'create'],
            // Precedence: the first guard that fails names the reason.
            'expired and repriced: expired' => [true, self::NOW - 1, false, null, $shipsReady, 'refuse:session_expired'],
            'expired with an order: expired' => [true, self::NOW - 1, true, 4711, $shipsReady, 'refuse:session_expired'],
            'shipping incomplete and repriced: shipping_incomplete' => [true, self::EXPIRES_AT, false, null, $shipsNoRate, 'refuse:shipping_incomplete'],
            'repriced with an order: cart_changed' => [true, self::EXPIRES_AT, false, 4711, $shipsReady, 'refuse:cart_changed'],
        ];
    }

    private function cart(bool $needsShipping, bool $destinationComplete, bool $rateChosen): CartFacts
    {
        return new CartFacts(
            [new CartLine(12, 2, false, 'Test Simple Product', Money::fromDecimal('20.00', 'EUR'), '21.00')],
            $needsShipping,
            $destinationComplete,
            $rateChosen,
            Money::fromDecimal($needsShipping ? '26.05' : '20.00', 'EUR'),
            [],
            [],
            $needsShipping ? new CartShipping(['flat_rate:3'], 'Standard', Money::fromDecimal('6.05', 'EUR'), '21.00') : null,
            'cart-hash-1',
            $needsShipping ? 'LU|1234|Town' : ''
        );
    }
}
