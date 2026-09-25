<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\AddressMapping;
use Mollie\WooCommerce\Core\Express\FirstSightEffects;
use Mollie\WooCommerce\Core\Types\Effect;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\MollieAddress;
use Mollie\WooCommerce\Core\Types\Money;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;
use Mollie\WooCommerceTests\TestCase;

/**
 * What the order is given the first time a matched express payment is seen (REQ-D1, D5, F1, F2;
 * AC-20, AC-24, AC-32, AC-33).
 *
 * As data, for the EffectInterpreter to apply under the per-order lock: the payment id as
 * _mollie_payment_id and as the transaction id, the payment mode, and the plugin's payment method
 * for the wallet that paid, looked up by payment.method in the wallets table. When the wallet has
 * no row, or its payment method is not registered, the provisional method set at submit is kept and
 * a note names the Mollie method: a paid order is never left unfulfilled over a label.
 *
 * Addresses, per address type: what the store held when the order was created wins; the wallet's
 * details fill only a type the order holds nothing for; and the shipping address of an order that
 * needs shipping is never changed, because its cost was calculated from it.
 *
 * The wallet rows are the real ones in config/express.php.
 *
 * @covers \Mollie\WooCommerce\Core\Express\FirstSightEffects
 */
class FirstSightEffectsTest extends TestCase
{
    private const REGISTERED = ['mollie_wc_gateway_applepay', 'mollie_wc_gateway_paypal', 'mollie_wc_gateway_ideal'];

    /**
     * Scenario: the order records the payment and gets the payment method of the wallet that paid
     *   Given a matched payment made with a wallet that has a row and a registered payment method
     *   When the first-sight effects are built
     *   Then they set _mollie_payment_id and the transaction id to the payment id
     *   And they set _mollie_payment_mode to the payment's mode
     *   And they set the order's payment method to that wallet's gateway
     *   And they add no note
     *
     * @dataProvider walletsWithAPaymentMethod
     * @covers \Mollie\WooCommerce\Core\Express\FirstSightEffects::for
     */
    public function testRecordsThePaymentAndSetsTheWalletsPaymentMethod(string $mollieMethod, string $expectedGateway): void
    {
        $effects = FirstSightEffects::for(
            $this->payment(['method' => $mollieMethod, 'mode' => 'live']),
            $this->order(),
            $this->wallets(),
            self::REGISTERED
        );

        $described = $this->described($effects);
        self::assertContains([Effect::SET_META, ['key' => '_mollie_payment_id', 'value' => 'tr_express1']], $described);
        self::assertContains([Effect::SET_TRANSACTION_ID, ['transactionId' => 'tr_express1']], $described);
        self::assertContains([Effect::SET_META, ['key' => '_mollie_payment_mode', 'value' => 'live']], $described);
        self::assertSame(
            [[Effect::SET_PAYMENT_METHOD, ['gatewayId' => $expectedGateway]]],
            $this->ofType($described, Effect::SET_PAYMENT_METHOD)
        );
        self::assertSame([], $this->ofType($described, Effect::ADD_NOTE));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function walletsWithAPaymentMethod(): array
    {
        return [
            'Apple Pay' => ['applepay', 'mollie_wc_gateway_applepay'],
            'PayPal' => ['paypal', 'mollie_wc_gateway_paypal'],
        ];
    }

    /**
     * Scenario: a method without a wallet payment method keeps the provisional one and is noted
     *   Given a matched payment whose method has no wallet row, or a row whose payment method is not registered
     *   When the first-sight effects are built
     *   Then they still record the payment id, the transaction id and the mode
     *   And they do not change the payment method
     *   And they add one note naming the Mollie method
     *
     * @dataProvider methodsWithoutAWalletPaymentMethod
     * @covers \Mollie\WooCommerce\Core\Express\FirstSightEffects::for
     */
    public function testKeepsTheProvisionalMethodAndNotesTheMollieMethod(string $mollieMethod): void
    {
        $effects = FirstSightEffects::for(
            $this->payment(['method' => $mollieMethod]),
            $this->order(),
            $this->wallets(),
            self::REGISTERED
        );

        $described = $this->described($effects);
        self::assertContains([Effect::SET_META, ['key' => '_mollie_payment_id', 'value' => 'tr_express1']], $described);
        self::assertContains([Effect::SET_TRANSACTION_ID, ['transactionId' => 'tr_express1']], $described);
        self::assertSame([], $this->ofType($described, Effect::SET_PAYMENT_METHOD));
        self::assertSame(
            [[Effect::ADD_NOTE, ['messageKey' => FirstSightEffects::NOTE_UNKNOWN_WALLET, 'params' => ['method' => $mollieMethod]]]],
            $this->ofType($described, Effect::ADD_NOTE)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function methodsWithoutAWalletPaymentMethod(): array
    {
        return [
            'no row in the wallets table' => ['creditcard'],
            'a row, but no registered payment method (Google Pay today)' => ['googlepay'],
        ];
    }

    /**
     * Scenario: the wallet's billing details fill only a billing address the order holds nothing for
     *   Given a matched payment carrying a billing address
     *   When the order holds no billing details, the effects set the billing address from the payment
     *   And when the order holds billing details, they set no billing address at all
     *
     * @dataProvider billingHeld
     * @covers \Mollie\WooCommerce\Core\Express\FirstSightEffects::for
     */
    public function testFillsOnlyABillingAddressTheOrderHoldsNothingFor(bool $orderHoldsBilling): void
    {
        $billing = $this->mollieAddress();

        $effects = FirstSightEffects::for(
            $this->payment(['billingAddress' => MollieAddress::fromArray($billing)]),
            $this->order(['holdsBilling' => $orderHoldsBilling]),
            $this->wallets(),
            self::REGISTERED
        );

        $billingEffects = array_values(array_filter(
            $this->ofType($this->described($effects), Effect::SET_ADDRESS),
            static fn (array $effect): bool => $effect[1]['addressType'] === 'billing'
        ));
        if ($orderHoldsBilling) {
            self::assertSame([], $billingEffects);

            return;
        }
        self::assertSame(
            [[Effect::SET_ADDRESS, ['addressType' => 'billing', 'fields' => AddressMapping::toWooCommerce($billing)]]],
            $billingEffects
        );
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function billingHeld(): array
    {
        return [
            'a guest who paid without filling the form' => [false],
            'billing held from the checkout form or the account' => [true],
        ];
    }

    /**
     * Scenario: the shipping address of an order that needs shipping is never changed
     *   Given a matched payment carrying a shipping address
     *   When the order needs shipping, the effects set no shipping address, even if the order holds none
     *   And when the order holds a shipping address, they set none either
     *   And only an order with nothing to ship and no shipping address takes the wallet's
     *
     * @dataProvider shippingCases
     * @covers \Mollie\WooCommerce\Core\Express\FirstSightEffects::for
     */
    public function testNeverChangesTheShippingAddressOfAnOrderThatNeedsShipping(
        bool $needsShipping,
        bool $holdsShipping,
        bool $expectShippingEffect
    ): void {

        $shipping = $this->mollieAddress(['city' => 'Rotterdam']);

        $effects = FirstSightEffects::for(
            $this->payment(['shippingAddress' => MollieAddress::fromArray($shipping)]),
            $this->order(['needsShipping' => $needsShipping, 'holdsShipping' => $holdsShipping]),
            $this->wallets(),
            self::REGISTERED
        );

        $shippingEffects = array_values(array_filter(
            $this->ofType($this->described($effects), Effect::SET_ADDRESS),
            static fn (array $effect): bool => $effect[1]['addressType'] === 'shipping'
        ));
        if (!$expectShippingEffect) {
            self::assertSame([], $shippingEffects);

            return;
        }
        self::assertSame(
            [[Effect::SET_ADDRESS, ['addressType' => 'shipping', 'fields' => AddressMapping::toWooCommerce($shipping)]]],
            $shippingEffects
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
            null,
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
            'holdsBilling' => true,
            'holdsShipping' => true,
            'needsShipping' => true,
        ], $overrides);

        return new ExpressOrderFacts(
            expressRef: 'exr_0123456789abcdef0123456789abcdef',
            existingOrderId: 42,
            createdVia: 'mollie_express',
            total: Money::fromDecimal('26.05', 'EUR'),
            trackedPaymentId: null,
            needsPayment: true,
            holdsBilling: $values['holdsBilling'],
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
     * @param array<int, Effect> $effects
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function described(array $effects): array
    {
        return array_map(static function (Effect $effect): array {
            return [$effect->type(), $effect->data()];
        }, $effects);
    }

    /**
     * @param array<int, array{0: string, 1: array<string, mixed>}> $described
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function ofType(array $described, string $type): array
    {
        return array_values(array_filter($described, static fn (array $effect): bool => $effect[0] === $type));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function wallets(): array
    {
        return (require PROJECT_DIR . '/config/express.php')['wallets'];
    }
}
