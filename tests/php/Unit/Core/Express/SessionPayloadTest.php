<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\SessionLines;
use Mollie\WooCommerce\Core\Express\SessionPayload;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\CartLine;
use Mollie\WooCommerce\Core\Types\CartShipping;
use Mollie\WooCommerce\Core\Types\Money;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\SessionRules;
use Mollie\WooCommerceTests\TestCase;

/**
 * The body of POST /v2/sessions (REQ-B4, REQ-G1).
 *
 * The plugin authenticates with an API key, so profileId and testmode are never sent. No order
 * exists yet, so the metadata is the per-session express_ref and nothing else. The wallet is asked
 * only for what the store does not already hold for this shopper, and never for a shipping address:
 * a cart that ships takes its address from the checkout form, one that does not needs none.
 *
 * @covers \Mollie\WooCommerce\Core\Express\SessionPayload
 */
class SessionPayloadTest extends TestCase
{
    private const REDIRECT_URL = 'https://shop.example/wc-api/mollie_express_return?ref=exr_1a2b3c';

    private const WEBHOOK_URL = 'https://shop.example/wp-json/mollie/v1/webhook?mollie_webhook_secret=secret';

    /**
     * Scenario: the body carries only what Mollie needs and the plugin may send
     *   Given a priced cart, its lines, the return and webhook URLs and an express_ref
     *   When the session payload is built
     *   Then it breaks none of the documented Sessions rules
     *   And it has no profileId and no testmode key
     *   And its metadata is exactly the express_ref
     *   And its amount is the cart total, its webhook URL and redirect URL are the ones given
     *   And its requiredCustomerDetails are exactly the ones it was given, the caller having decided
     *
     * @dataProvider requestedDetails
     * @covers \Mollie\WooCommerce\Core\Express\SessionPayload::build
     * @param list<string> $requested
     * @param list<string> $expected
     */
    public function testNeverSendsProfileIdTestmodeOrPersonalMetadata(array $requested, array $expected): void
    {
        $cart = $this->cart();

        $payload = SessionPayload::build(
            $cart,
            SessionLines::fromCart($cart),
            self::REDIRECT_URL,
            self::WEBHOOK_URL,
            ['express_ref' => 'exr_1a2b3c'],
            $requested
        );

        self::assertSame([], SessionRules::violations($payload), 'The payload breaks the documented Sessions rules.');
        self::assertArrayNotHasKey('profileId', $payload);
        self::assertArrayNotHasKey('testmode', $payload);
        self::assertSame(['express_ref' => 'exr_1a2b3c'], $payload['metadata']);
        self::assertSame(['currency' => 'EUR', 'value' => '26.05'], $payload['amount']);
        self::assertSame(self::REDIRECT_URL, $payload['redirectUrl']);
        self::assertSame(self::WEBHOOK_URL, $payload['payment']['webhookUrl']);
        self::assertSame($expected, $payload['requiredCustomerDetails'] ?? []);
    }

    /**
     * @return array<string, array{0: list<string>, 1: list<string>}>
     */
    public function requestedDetails(): array
    {
        return [
            'email and billing address' => [['email', 'billing-address'], ['email', 'billing-address']],
            'nothing' => [[], []],
            // Which details are asked for is requiredCustomerDetails' decision, not build's.
            'a shipping address is passed through' => [
                ['email', 'shipping-address'],
                ['email', 'shipping-address'],
            ],
        ];
    }

    /**
     * Scenario: the wallet is always asked for the contact details and the billing address
     *   Given the store may or may not already hold the shopper's email and billing address
     *   When the required customer details are decided
     *   Then email and billing-address are asked for either way
     *   And shipping-address is never asked for
     *
     * The sheet governs which contact and billing address the shopper picks, so what comes back
     * takes precedence over anything stored (owner, 2026-09-24, revising REQ-C2). The shipping
     * address is never asked for: the session's amount is fixed when it is created and Mollie has
     * no event for an address changed in the sheet, so it could not be priced.
     *
     * @covers \Mollie\WooCommerce\Core\Express\SessionPayload::requiredCustomerDetails
     */
    public function testAlwaysAsksTheWalletForContactAndBilling(): void
    {
        self::assertSame(['email', 'billing-address'], SessionPayload::requiredCustomerDetails());
    }

    /**
     * Scenario: the wallet is never asked where to ship
     *   When the required customer details are decided
     *   Then shipping-address is not among them
     *
     * A cart that ships was quoted for the checkout form's address and a session's amount cannot be
     * repriced; a cart with nothing to ship has nowhere to ship to *(owner, 2026-09-24)*.
     *
     * @covers \Mollie\WooCommerce\Core\Express\SessionPayload::requiredCustomerDetails
     */
    public function testNeverAsksTheWalletWhereToShip(): void
    {
        self::assertNotContains('shipping-address', SessionPayload::requiredCustomerDetails());
    }

    private function cart(): CartFacts
    {
        $eur = static fn (string $value): Money => Money::fromDecimal($value, 'EUR');

        return new CartFacts(
            lines: [new CartLine(
                productId: 7,
                quantity: 2,
                isSubscription: false,
                name: 'Test Simple Product',
                subtotal: $eur('20.00'),
                vatRate: '21.00'
            )],
            needsShipping: true,
            shippingDestinationComplete: true,
            shippingRateChosen: true,
            total: $eur('26.05'),
            fees: [],
            coupons: [],
            shipping: new CartShipping(['flat_rate:1'], 'Flat rate', $eur('6.05'), '21.00'),
            cartHash: 'cart-hash',
            destination: 'LU|1234|Town'
        );
    }
}
