<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Admission;
use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\Refuse;
use Mollie\WooCommerceTests\TestCase;

/**
 * Who may call an express entry point: a verified nonce admits, anything unknown is refused.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\Admission
 */
class AdmissionTest extends TestCase
{
    /**
     * Scenario: the session route is admitted only with a verified nonce
     *   Given an entry point and whether a nonce was sent and verified
     *   When admission is decided
     *   Then the express session route with a verified nonce is admitted
     *   And a missing or unverified nonce, or an unknown entry point, is refused with 403 and its reason
     *
     * @dataProvider requests
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\Admission::admit
     */
    public function testAdmitsTheSessionRouteOnlyWithAValidNonce(
        string $entryPoint,
        bool $noncePresent,
        bool $nonceValid,
        ?string $expectedRefusal
    ): void {

        $decision = Admission::admit($entryPoint, $noncePresent, $nonceValid);

        if ($expectedRefusal === null) {
            self::assertInstanceOf(Admit::class, $decision);
            return;
        }
        self::assertInstanceOf(Refuse::class, $decision);
        self::assertSame($expectedRefusal, $decision->code());
        self::assertSame(403, $decision->httpStatus());
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool, 3: ?string}>
     */
    public function requests(): array
    {
        return [
            'a verified nonce' => [Admission::EXPRESS_SESSION, true, true, null],
            'no nonce' => [Admission::EXPRESS_SESSION, false, false, 'nonce_missing'],
            'a nonce that does not verify' => [Admission::EXPRESS_SESSION, true, false, 'nonce_invalid'],
            'the order route, a verified nonce' => [Admission::EXPRESS_ORDER, true, true, null],
            'the order route, no nonce' => [Admission::EXPRESS_ORDER, false, false, 'nonce_missing'],
            'the order route, a nonce that does not verify' => [Admission::EXPRESS_ORDER, true, false, 'nonce_invalid'],
            'an unknown entry point, even with a verified nonce' => ['express.unknown', true, true, 'unknown_entry_point'],
        ];
    }
}
