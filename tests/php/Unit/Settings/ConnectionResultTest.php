<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Settings;

use InvalidArgumentException;
use Mollie\WooCommerce\Settings\ConnectionResult;
use Mollie\WooCommerceTests\TestCase;

/**
 * The outcome of the Mollie connection check as a value (review of PIWOO-938): the failure
 * kinds are a closed set, checked when the value is made.
 *
 * @covers \Mollie\WooCommerce\Settings\ConnectionResult
 */
class ConnectionResultTest extends TestCase
{
    /**
     * Scenario: a failure keeps what it was given
     *   Given each known failure kind with a code and a message
     *   When a failed result is made
     *   Then it is not connected and returns the kind, code and message unchanged
     *
     * @dataProvider kinds
     */
    public function testAFailureKeepsItsKindCodeAndMessage(string $kind): void
    {
        $result = ConnectionResult::failed($kind, 503, 'Down <b>now</b>');

        self::assertFalse($result->isConnected());
        self::assertSame($kind, $result->errorKind());
        self::assertSame(503, $result->errorCode());
        self::assertSame('Down <b>now</b>', $result->errorMessage());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function kinds(): array
    {
        return [
            'incompatible' => [ConnectionResult::KIND_INCOMPATIBLE],
            'api key' => [ConnectionResult::KIND_API_KEY],
            'api' => [ConnectionResult::KIND_API],
        ];
    }

    /**
     * Scenario: an unknown kind is refused when the value is made
     *   Given a kind outside the known set
     *   When a failed result is made with it
     *   Then an InvalidArgumentException is thrown
     */
    public function testRefusesAnUnknownKind(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionResult::failed('network', 0, '');
    }

    /**
     * Scenario: a connected result carries no failure
     *   Given a connected result
     *   Then it has no kind, code 0 and an empty message
     */
    public function testAConnectedResultCarriesNoFailure(): void
    {
        $result = ConnectionResult::connected();

        self::assertTrue($result->isConnected());
        self::assertNull($result->errorKind());
        self::assertSame(0, $result->errorCode());
        self::assertSame('', $result->errorMessage());
    }

    /**
     * Scenario: a bare status becomes a result
     *   Given only whether the check passed
     *   When a result is made from it
     *   Then true is connected, and false is a key failure without detail, which renders the
     *     long-standing "check your API keys" message
     */
    public function testABareStatusBecomesAResult(): void
    {
        self::assertTrue(ConnectionResult::fromStatus(true)->isConnected());

        $failed = ConnectionResult::fromStatus(false);
        self::assertFalse($failed->isConnected());
        self::assertSame(ConnectionResult::KIND_API_KEY, $failed->errorKind());
        self::assertSame(0, $failed->errorCode());
        self::assertSame('', $failed->errorMessage());
    }
}
