<?php

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
     * @group REQ-F2
     * @dataProvider walletsWithAPaymentMethod
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     */
    public function testRecordsThePaymentAndSetsTheWalletsPaymentMethod(string $mollieMethod, ?string $wallet, string $expectedGateway): void
    {
        $verdict = FirstSight::decide(
            $this->payment(['method' => $mollieMethod, 'wallet' => $wallet, 'mode' => 'live']),
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
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public function walletsWithAPaymentMethod(): array
    {
        return [
            'Apple Pay' => ['applepay', null, 'mollie_wc_gateway_applepay'],
            'Apple Pay, reported as a card payment' => ['creditcard', 'applepay', 'mollie_wc_gateway_applepay'],
            'PayPal' => ['paypal', null, 'mollie_wc_gateway_paypal'],
            'Google Pay, reported as a card payment' => ['creditcard', 'googlepay', 'mollie_wc_gateway_googlepay'],
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
     * Scenario: a card payment without a wallet keeps the provisional method
     *   Given a matched payment that Mollie reports as creditcard with no wallet
     *   And every wallet's payment method registered
     *   When the first sight is decided
     *   Then the verdict names no gateway
     *   And names creditcard as the Mollie method, for the note
     *
     * @group REQ-F2
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     */
    public function testACardPaymentWithoutAWalletKeepsTheProvisionalMethod(): void
    {
        $verdict = FirstSight::decide(
            $this->payment(['method' => 'creditcard', 'wallet' => null]),
            $this->order(),
            $this->wallets(),
            self::REGISTERED
        );

        self::assertNull($verdict->gatewayId());
        self::assertSame('creditcard', $verdict->unmatchedMethod());
    }

    /**
     * Scenario: a card payment whose wallet cannot be matched keeps the provisional method
     *   Given a matched payment that Mollie reports as creditcard with a wallet that has no row,
     *     or with a wallet whose payment method is not registered
     *   When the first sight is decided
     *   Then the verdict names no gateway
     *   And names creditcard as the Mollie method, for the note
     *
     * @group REQ-F2
     * @dataProvider cardPaymentsWithAnUnmatchedWallet
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     * @param list<string> $registered
     */
    public function testACardPaymentWithAnUnmatchedWalletKeepsTheProvisionalMethod(string $wallet, array $registered): void
    {
        $verdict = FirstSight::decide(
            $this->payment(['method' => 'creditcard', 'wallet' => $wallet]),
            $this->order(),
            $this->wallets(),
            $registered
        );

        self::assertNull($verdict->gatewayId());
        self::assertSame('creditcard', $verdict->unmatchedMethod());
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public function cardPaymentsWithAnUnmatchedWallet(): array
    {
        return [
            'a wallet with no row' => ['samsungpay', self::REGISTERED],
            'a known wallet whose payment method is not registered' => ['applepay', ['mollie_wc_gateway_ideal']],
        ];
    }

    /**
     * Scenario: the title check of any gateway matches only its own gateway
     *   Given the registered list of one gateway, as the order service passes it
     *   When a payment is matched against the wallets table
     *   Then a card payment made with a wallet matches that wallet's gateway only when it is the registered one
     *   And a gateway outside the table matches nothing
     *
     * @group REQ-F2
     * @dataProvider registeredGatewayAlone
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::walletGateway
     */
    public function testAGatewayAloneMatchesOnlyItsOwnWallet(string $gatewayId, string $method, ?string $wallet, ?string $expected): void
    {
        self::assertSame($expected, FirstSight::walletGateway($method, $wallet, $this->wallets(), [$gatewayId]));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: ?string}>
     */
    public function registeredGatewayAlone(): array
    {
        return [
            'Apple Pay gateway, card with its wallet' => ['mollie_wc_gateway_applepay', 'creditcard', 'applepay', 'mollie_wc_gateway_applepay'],
            'Google Pay gateway, card with the Apple Pay wallet' => ['mollie_wc_gateway_googlepay', 'creditcard', 'applepay', null],
            'iDEAL gateway, card with a wallet' => ['mollie_wc_gateway_ideal', 'creditcard', 'applepay', null],
            'iDEAL gateway, an iDEAL payment' => ['mollie_wc_gateway_ideal', 'ideal', null, null],
        ];
    }

    /**
     * Scenario: every wallet row names what Mollie reports on a payment made with it
     *   Given the wallets table in config/express.php
     *   When its rows are read
     *   Then Apple Pay is paid as applepay or as a card with wallet applepay, PayPal as paypal,
     *     and Google Pay only as a card with wallet googlepay
     *
     * @group REQ-F2
     * @coversNothing
     */
    public function testEveryWalletRowNamesWhatMollieReportsForIt(): void
    {
        $reported = array_map(
            static fn (array $row): array => [$row['paidAs'] ?? null, $row['paidWallet'] ?? null],
            $this->wallets()
        );

        self::assertSame(
            ['applepay' => ['applepay', 'applepay'], 'paypal' => ['paypal', null], 'googlepay' => [null, 'googlepay']],
            $reported
        );
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
     * @group REQ-C2
     * @group REQ-D5
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
     * Scenario: an order that ships takes the wallet's shipping address, one that ships nothing takes none
     *   Given a matched payment carrying a shipping address
     *   When the order needs shipping, the verdict carries the wallet's shipping fields, whether or not
     *        the order held an address before: the shopper chose it in the sheet
     *   And when the order ships nothing, the verdict carries no shipping fields
     *
     * @dataProvider shippingCases
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     * @group REQ-C3
     */
    public function testOnlyAnOrderThatShipsTakesTheWalletsShippingAddress(
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
            'needs shipping, address held' => [true, true, true],
            'needs shipping, no address held' => [true, false, true],
            'nothing to ship, address held' => [false, true, false],
            'nothing to ship, no address held' => [false, false, false],
        ];
    }

    /**
     * Scenario: a billing address without a postal address falls back to the shipping address
     *   Given a matched payment whose billing address holds an email only (PayPal) and a shipping address
     *   When the first sight is decided
     *   Then the billing fields are the shipping address's, with the wallet's email
     *   And a full billing address is written as sent
     *
     * @dataProvider billingFromTheWallet
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\FirstSight::decide
     * @group REQ-D5
     * @group REQ-C2
     * @param array<string, string> $billing
     * @param array<string, string> $shipping
     * @param array<string, string> $expected Mollie fields of the expected billing address.
     */
    public function testBillingFallsBackToTheShippingAddressWhenTheWalletSendsOnlyAnEmail(
        array $billing,
        array $shipping,
        array $expected
    ): void {
        $verdict = FirstSight::decide(
            $this->payment([
                'billingAddress' => MollieAddress::fromArray($billing),
                'shippingAddress' => $shipping === [] ? null : MollieAddress::fromArray($shipping),
            ]),
            $this->order(),
            $this->wallets(),
            self::REGISTERED
        );

        self::assertSame(AddressMapping::toWooCommerce($expected), $verdict->billing());
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: array<string, string>, 2: array<string, string>}>
     */
    public function billingFromTheWallet(): array
    {
        $shipping = $this->mollieAddress(['email' => 'ship@example.org', 'city' => 'Utrecht']);
        $full = $this->mollieAddress(['email' => 'bill@example.org', 'city' => 'Rotterdam']);

        return [
            'email only: shipping fields, the wallet email' => [
                ['email' => 'bill@example.org'],
                $shipping,
                array_merge($shipping, ['email' => 'bill@example.org']),
            ],
            'a full billing address is written as sent' => [$full, $shipping, $full],
            'email only and no shipping address: the email alone' => [
                ['email' => 'bill@example.org'],
                [],
                ['email' => 'bill@example.org'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function payment(array $overrides = []): PaymentSnapshot
    {
        $values = array_merge([
            'method' => 'paypal',
            'wallet' => null,
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
            shippingAddress: $values['shippingAddress'],
            wallet: $values['wallet']
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
