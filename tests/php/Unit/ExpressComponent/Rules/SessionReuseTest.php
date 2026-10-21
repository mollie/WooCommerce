<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\SessionReuse;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether the shopper's open session is handed out again instead of creating a new one.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionReuse
 */
class SessionReuseTest extends TestCase
{
    private const NOW = 1790000000;

    private const MARGIN = 60;

    /**
     * Scenario: an open session is reused only for the same price and account, with time left and no settled order
     *   Given the remembered session's fingerprint, account and expiry, the cart's fingerprint, the shop's account,
     *   the time, the margin, and whether the session's order is already paid or cancelled
     *   When reuse is asked
     *   Then it fits only when fingerprints and accounts are equal, the session outlives the margin
     *   and its order is not settled
     *
     * @dataProvider sessions
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionReuse::fits
     */
    public function testReusesASessionOnlyForTheSamePriceWithTimeLeft(
        string $sessionFingerprint,
        string $sessionAccount,
        int $secondsLeft,
        bool $orderSettled,
        bool $expected
    ): void {

        self::assertSame(
            $expected,
            SessionReuse::fits(
                $sessionFingerprint,
                $sessionAccount,
                self::NOW + $secondsLeft,
                'fingerprint',
                'live:pfl_shop',
                self::NOW,
                self::MARGIN,
                $orderSettled
            )
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: bool, 4: bool}>
     */
    public function sessions(): array
    {
        return [
            'the same price, ten minutes left' => ['fingerprint', 'live:pfl_shop', 600, false, true],
            'the same price, one second more than the margin' => ['fingerprint', 'live:pfl_shop', self::MARGIN + 1, false, true],
            'the same price, exactly the margin left' => ['fingerprint', 'live:pfl_shop', self::MARGIN, false, false],
            'the same price, less than the margin left' => ['fingerprint', 'live:pfl_shop', 30, false, false],
            'the same price, already expired' => ['fingerprint', 'live:pfl_shop', -1, false, false],
            'another price, ten minutes left' => ['fingerprint-of-another-checkout', 'live:pfl_shop', 600, false, false],
            'the same price, ten minutes left, another profile' => ['fingerprint', 'live:pfl_other', 600, false, false],
            'the same price, ten minutes left, the other mode' => ['fingerprint', 'test:pfl_shop', 600, false, false],
            'the same price, ten minutes left, remembered before accounts were' => ['fingerprint', '', 600, false, false],
            'the same price, ten minutes left, its order paid or cancelled' => ['fingerprint', 'live:pfl_shop', 600, true, false],
        ];
    }
}
