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
     * Scenario: an open session is reused only for the same price and with more than the margin left
     *   Given the remembered session's fingerprint and expiry, the cart's fingerprint, the time and the margin
     *   When reuse is asked
     *   Then it fits only when the fingerprints are equal and the session outlives the margin
     *
     * @dataProvider sessions
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionReuse::fits
     */
    public function testReusesASessionOnlyForTheSamePriceWithTimeLeft(string $sessionFingerprint, int $secondsLeft, bool $expected): void
    {
        self::assertSame(
            $expected,
            SessionReuse::fits($sessionFingerprint, self::NOW + $secondsLeft, 'fingerprint', self::NOW, self::MARGIN)
        );
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: bool}>
     */
    public function sessions(): array
    {
        return [
            'the same price, ten minutes left' => ['fingerprint', 600, true],
            'the same price, one second more than the margin' => ['fingerprint', self::MARGIN + 1, true],
            'the same price, exactly the margin left' => ['fingerprint', self::MARGIN, false],
            'the same price, less than the margin left' => ['fingerprint', 30, false],
            'the same price, already expired' => ['fingerprint', -1, false],
            'another price, ten minutes left' => ['fingerprint-of-another-checkout', 600, false],
        ];
    }
}
