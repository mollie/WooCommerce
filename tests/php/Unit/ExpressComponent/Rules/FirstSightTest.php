<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\AddressMapping;
use Mollie\WooCommerce\ExpressComponent\Rules\FirstSight;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\FirstSightData;
use Mollie\WooCommerce\Shared\Values\MollieAddress;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use Mollie\WooCommerceTests\TestCase;

/**
 * What the order is given the first time a matched express payment is seen: payment id, mode,
 * the wallet's payment method and addresses. Uses the real wallet rows in config/express.php.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight
 */
class FirstSightTest extends TestCase
{
    private const REGISTERED = [
        'mollie_wc_gateway_applepay',
        'mollie_wc_gateway_paypal',
        'mollie_wc_gateway_googlepay',
        'mollie_wc_gateway_ideal',
    ];

    /**
     * Scenario: the order records the payment and gets the payment method of the wallet that paid
     *   Given a matched payment made with a wallet that has a row and a registered payment method
     *   When the first sight is decided
     *   Then the verdict names the payment id and the payment's mode
     *   And the wallet's gateway
     *   And no Mollie method to note
     *   And, the payment carrying no address and the order shipping, no billing and no shipping fields
     *
     * @dataProvider walletsWithAPaymentMethod
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     */
    public function testRecordsThePaymentAndSetsTheWalletsPaymentMethod(string $mollieMethod, string $expectedGateway): void
    {
        $verdict = FirstSight::decide(
            $this->payment(['method' => $mollieMethod, 'mode' => 'live']),
            $this->order(),
            $this->wallets(),
            self::REGISTERED
        );

        self::assertInstanceOf(FirstSightData::class, $verdict);
        self::assertSame('tr_express1', $verdict->paymentId());
        self::assertSame('live', $verdict->mode());
        self::assertSame($expectedGateway, $verdict->gatewayId());
        self::assertNull($verdict->unmatchedMethod());
        self::assertSame([], $verdict->billing(), 'A payment without a billing address gives no billing fields.');
        self::assertNull($verdict->shipping(), 'An order that ships gets no shipping fields.');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function walletsWithAPaymentMethod(): array
    {
        return [
            'Apple Pay' => ['applepay', 'mollie_wc_gateway_applepay'],
            'PayPal' => ['paypal', 'mollie_wc_gateway_paypal'],
            'Google Pay, reported as a card payment' => ['creditcard', 'mollie_wc_gateway_googlepay'],
        ];
    }

    /**
     * Scenario: a method without a wallet payment method keeps the provisional one and is noted
     *   Given a matched payment whose method has no wallet row, or a row whose payment method is not registered
     *   When the first sight is decided
     *   Then the verdict still names the payment id and the mode
     *   And no gateway, so the payment method is not changed
     *   And the Mollie method, for the note
     *
     * @dataProvider methodsWithoutAWalletPaymentMethod
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     */
    public function testKeepsTheProvisionalMethodAndNotesTheMollieMethod(string $mollieMethod): void
    {
        $verdict = FirstSight::decide(
            $this->payment(['method' => $mollieMethod]),
            $this->order(),
            $this->wallets(),
            self::REGISTERED
        );

        self::assertSame('tr_express1', $verdict->paymentId());
        self::assertSame('live', $verdict->mode());
        self::assertNull($verdict->gatewayId());
        self::assertSame($mollieMethod, $verdict->unmatchedMethod());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function methodsWithoutAWalletPaymentMethod(): array
    {
        return [
            'no row in the wallets table' => ['ideal'],
            'googlepay is never what Mollie reports on a payment' => ['googlepay'],
        ];
    }

    /**
     * Scenario: a card payment keeps the provisional method while the plugin has no Google Pay method
     *   Given a matched payment that Mollie reports as creditcard
     *   And no registered Google Pay payment method
     *   When the first sight is decided
     *   Then the verdict names no gateway
     *   And names creditcard as the Mollie method, for the note
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     */
    public function testACardPaymentKeepsTheProvisionalMethodWhileGooglePayIsNotRegistered(): void
    {
        $registered = array_values(array_diff(self::REGISTERED, ['mollie_wc_gateway_googlepay']));

        $verdict = FirstSight::decide(
            $this->payment(['method' => 'creditcard']),
            $this->order(),
            $this->wallets(),
            $registered
        );

        self::assertNull($verdict->gatewayId());
        self::assertSame('creditcard', $verdict->unmatchedMethod());
    }

    /**
     * Scenario: every wallet row names the method Mollie reports on a payment made with it
     *   Given the wallets table in config/express.php
     *   When its rows are read
     *   Then Apple Pay is paid as applepay, PayPal as paypal, and Google Pay as creditcard
     *
     * @coversNothing
     */
    public function testEveryWalletRowNamesTheMethodMollieReportsForIt(): void
    {
        $paidAs = array_map(static fn (array $row): ?string => $row['paidAs'] ?? null, $this->wallets());

        self::assertSame(['applepay' => 'applepay', 'paypal' => 'paypal', 'googlepay' => 'creditcard'], $paidAs);
    }

    /**
     * Scenario: the wallet's billing details replace whatever the order holds
     *   Given a matched payment carrying a billing address
     *   When the first sight is decided
     *   Then the verdict's billing fields are the payment's, in WooCommerce field names, whatever the order held before
     *
     * The sheet is where the shopper chose that address, so it wins over the form and the account.
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     */
    public function testTheWalletsBillingAddressReplacesTheOrdersOwn(): void
    {
        $billing = $this->mollieAddress();

        $verdict = FirstSight::decide(
            $this->payment(['billingAddress' => MollieAddress::fromArray($billing)]),
            $this->order(),
            $this->wallets(),
            self::REGISTERED
        );

        self::assertSame(AddressMapping::toWooCommerce($billing), $verdict->billing());
    }

    /**
     * Scenario: only an order that ships keeps its shipping address from the wallet's
     *   Given a matched payment carrying a shipping address
     *   When the order needs shipping, the verdict carries no shipping fields, even if it holds none:
     *        that order was quoted a shipping cost for the address it already has
     *   And only an order with nothing to ship and no address of its own takes the wallet's, which
     *        in practice never arrives: the wallet is not asked where to ship
     *
     * @dataProvider shippingCases
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     */
    public function testOnlyAnOrderThatShipsKeepsItsShippingAddress(
        bool $needsShipping,
        bool $holdsShipping,
        bool $expectShippingFields
    ): void {

        $shipping = $this->mollieAddress(['city' => 'Rotterdam']);

        $verdict = FirstSight::decide(
            $this->payment(['shippingAddress' => MollieAddress::fromArray($shipping)]),
            $this->order(['needsShipping' => $needsShipping, 'holdsShipping' => $holdsShipping]),
            $this->wallets(),
            self::REGISTERED
        );

        self::assertSame(
            $expectShippingFields ? AddressMapping::toWooCommerce($shipping) : null,
            $verdict->shipping()
        );
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool}>
     */
    public function shippingCases(): array
    {
        return [
            'needs shipping, address held' => [true, true, false],
            'needs shipping, no address held' => [true, false, false],
            'nothing to ship, address held' => [false, true, false],
            'nothing to ship, no address held' => [false, false, true],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function payment(array $overrides = []): PaymentSnapshot
    {
        $values = array_merge([
            'method' => 'paypal',
            'mode' => 'live',
            'billingAddress' => null,
            'shippingAddress' => null,
        ], $overrides);

        return new PaymentSnapshot(
            'tr_express1',
            'paid',
            $values['method'],
            Money::fromDecimal('26.05', 'EUR'),
            mode: $values['mode'],
            expressRef: 'exr_0123456789abcdef0123456789abcdef',
            billingAddress: $values['billingAddress'],
            shippingAddress: $values['shippingAddress']
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides = []): ExpressOrderFacts
    {
        $values = array_merge([
            'holdsShipping' => true,
            'needsShipping' => true,
        ], $overrides);

        return new ExpressOrderFacts(
            orderId: 42,
            expressRef: 'exr_0123456789abcdef0123456789abcdef',
            createdVia: 'mollie_express',
            total: Money::fromDecimal('26.05', 'EUR'),
            trackedPaymentId: null,
            needsPayment: true,
            holdsShipping: $values['holdsShipping'],
            needsShipping: $values['needsShipping']
        );
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function mollieAddress(array $overrides = []): array
    {
        return array_merge([
            'givenName' => 'Piet',
            'familyName' => 'Mondriaan',
            'email' => 'piet@example.org',
            'phone' => '+31208202070',
            'streetAndNumber' => 'Keizersgracht 12',
            'streetAdditional' => 'Unit 3',
            'postalCode' => '1015 CS',
            'city' => 'Amsterdam',
            'region' => 'Noord-Holland',
            'country' => 'NL',
        ], $overrides);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function wallets(): array
    {
        return (require PROJECT_DIR . '/config/express.php')['wallets'];
    }
}
