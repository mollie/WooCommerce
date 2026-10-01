<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\SessionLines;
use Mollie\WooCommerce\ExpressComponent\Rules\SessionPayload;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartLine;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartShipping;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\SessionRules;
use Mollie\WooCommerceTests\TestCase;

/**
 * The body of POST /v2/sessions and the customer details asked from the wallet.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionPayload
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
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionPayload::build
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
     * The session's amount is fixed at creation, so a shipping address from the sheet could not be priced.
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionPayload::requiredCustomerDetails
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
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionPayload::requiredCustomerDetails
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
