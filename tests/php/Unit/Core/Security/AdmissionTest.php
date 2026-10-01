<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Security;

use Mollie\WooCommerce\Core\Security\Admission;
use Mollie\WooCommerce\Core\Types\Admit;
use Mollie\WooCommerce\Core\Types\Refuse;
use Mollie\WooCommerceTests\TestCase;

/**
 * Who may call an entry point (blueprint chokepoint 1, ADR-013, REQ-G3).
 *
 * The request is parsed once in the adapter; Admission decides from the parsed facts only, and
 * never what the request may contain: the shape of the input is the route's own args schema. The
 * express session route is anonymous by design, so a verified nonce is the whole of its admission.
 * Anything the table does not know is refused.
 *
 * @covers \Mollie\WooCommerce\Core\Security\Admission
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
     * @covers \Mollie\WooCommerce\Core\Security\Admission::decide
     */
    public function testAdmitsTheSessionRouteOnlyWithAValidNonce(
        string $entryPoint,
        bool $noncePresent,
        bool $nonceValid,
        ?string $expectedRefusal
    ): void {

        $decision = Admission::decide($entryPoint, $noncePresent, $nonceValid);

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
