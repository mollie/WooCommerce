<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Rules;

use Mollie\WooCommerce\Payment\Rules\PendingPaymentHold;
use Mollie\WooCommerceTests\TestCase;

/**
 * @covers \Mollie\WooCommerce\Payment\Rules\PendingPaymentHold
 */
class PendingPaymentHoldTest extends TestCase
{
    /**
     * Scenario: the order is held only while a Pay by Bank payment is pending at Mollie
     *   Given a Mollie payment status and method, the order's status and the method's initial order status
     *   When holds() is asked
     *   Then it says yes only for a pending Pay by Bank payment of a pending order whose method starts on-hold
     *
     * @dataProvider cases
     * @covers \Mollie\WooCommerce\Payment\Rules\PendingPaymentHold::holds
     */
    public function testHoldsOnlyAPendingPayByBankPaymentOfAPendingOrderWithOnHoldInitialStatus(
        string $mollieStatus,
        string $mollieMethod,
        string $orderStatus,
        string $initialOrderStatus,
        bool $expected
    ): void {

        $answer = PendingPaymentHold::holds($mollieStatus, $mollieMethod, $orderStatus, $initialOrderStatus);

        self::assertSame($expected, $answer);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}>
     */
    public function cases(): array
    {
        $cases = [
            'pending Pay by Bank, pending order, on-hold initial status' => ['pending', 'paybybank', 'pending', 'on-hold', true],
            'initial status pending' => ['pending', 'paybybank', 'pending', 'pending', false],
            'initial status empty' => ['pending', 'paybybank', 'pending', '', false],
        ];
        foreach (['open', 'authorized', 'paid', 'canceled', 'expired', 'failed', ''] as $mollieStatus) {
            $cases["payment {$mollieStatus}"] = [$mollieStatus, 'paybybank', 'pending', 'on-hold', false];
        }
        foreach (['banktransfer', 'directdebit', 'ideal', 'bancontact', 'eps', 'belfius', 'billink', ''] as $method) {
            $cases["method {$method}"] = ['pending', $method, 'pending', 'on-hold', false];
        }
        foreach (['on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'] as $orderStatus) {
            $cases["order {$orderStatus}"] = ['pending', 'paybybank', $orderStatus, 'on-hold', false];
        }

        return $cases;
    }
}
