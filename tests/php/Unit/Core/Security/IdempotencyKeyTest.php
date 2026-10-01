<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Security;

use Mollie\WooCommerce\Core\Security\IdempotencyKey;
use Mollie\WooCommerceTests\TestCase;

/**
 * The deterministic idempotency key every mutating Mollie call carries (blueprint ADR-012).
 *
 * Three properties matter and are pinned here: the same intent and parts always produce the same
 * key, so a retried intent is refused by Mollie instead of moving money twice; any change to the
 * intent version or to a part produces a different key, so a legitimate second attempt is not
 * mistaken for a retry; and the key reveals none of its inputs, because it travels in a header
 * and shows up in Mollie's dashboard (ADR-012: "key parts must never include personal data").
 *
 * The prefix itself is phase 03's choice. These tests pin its shape, not its spelling.
 *
 * @covers \Mollie\WooCommerce\Core\Security\IdempotencyKey
 */
class IdempotencyKeyTest extends TestCase
{
    /**
     * A key is a recognisable prefix plus a sha256 digest, and nothing else.
     */
    private const KEY_SHAPE = '/^[a-z0-9]+(?:[.\-][a-z0-9]+)*-[0-9a-f]{64}$/';

    private const INTENT = 'express.session.v1';

    /**
     * @return array<string, mixed>
     */
    private function parts(): array
    {
        return ['order' => 4711, 'attempt' => 1, 'amount' => '20.00', 'currency' => 'EUR'];
    }

    /**
     * Scenario: the same intent and the same parts always produce the same key
     *   Given an intent name and its scalar parts
     *   When a key is built from them twice
     *   Then both keys are identical
     *   And the key is a recognisable prefix followed by a sha256 digest
     *
     * @covers \Mollie\WooCommerce\Core\Security\IdempotencyKey::for
     */
    public function testReturnsTheSameKeyForTheSameIntentAndParts(): void
    {
        $first = IdempotencyKey::for(self::INTENT, $this->parts());
        $second = IdempotencyKey::for(self::INTENT, $this->parts());

        self::assertSame($first, $second, 'A repeated intent must produce the key Mollie already saw.');
        self::assertRegExp(
            self::KEY_SHAPE,
            $first,
            'A key is a recognisable prefix plus a sha256 digest, so it can be found in the Mollie dashboard.'
        );
        self::assertLessThanOrEqual(255, strlen($first), 'The key travels in an HTTP header.');
    }

    /**
     * Scenario: anything that makes this a different intent makes a different key
     *   Given a baseline key
     *   When the intent version changes, or any part is added, changed or renamed
     *   Then the key differs from the baseline
     *
     * @dataProvider differentIntents
     * @covers \Mollie\WooCommerce\Core\Security\IdempotencyKey::for
     * @param array<string, mixed> $parts
     */
    public function testReturnsADifferentKeyWhenTheIntentVersionOrAnyPartChanges(
        string $intent,
        array $parts,
        string $why
    ): void {

        $baseline = IdempotencyKey::for(self::INTENT, $this->parts());

        self::assertNotSame($baseline, IdempotencyKey::for($intent, $parts), $why);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public function differentIntents(): array
    {
        $parts = ['order' => 4711, 'attempt' => 1, 'amount' => '20.00', 'currency' => 'EUR'];

        $bumpedAttempt = $parts;
        $bumpedAttempt['attempt'] = 2;

        $otherAmount = $parts;
        $otherAmount['amount'] = '20.01';

        $otherOrder = $parts;
        $otherOrder['order'] = 4712;

        $renamedPart = $parts;
        unset($renamedPart['order']);
        $renamedPart['order_id'] = 4711;

        $extraPart = $parts;
        $extraPart['method'] = 'applepay';

        $fewerParts = $parts;
        unset($fewerParts['currency']);

        return [
            'the intent version was bumped' => [
                'express.session.v2',
                $parts,
                'A new version of an intent is a new intent.',
            ],
            'a second attempt for the same order' => [
                self::INTENT,
                $bumpedAttempt,
                'ADR-012: a legitimate second attempt must change the key.',
            ],
            'the amount changed' => [
                self::INTENT,
                $otherAmount,
                'Changing the amount must produce a new key.',
            ],
            'another order' => [
                self::INTENT,
                $otherOrder,
                'Two orders must never share a key.',
            ],
            'the same value under another part name' => [
                self::INTENT,
                $renamedPart,
                'Part names are part of the intent; only hashing the values would collide.',
            ],
            'one part more' => [
                self::INTENT,
                $extraPart,
                'An added part must change the key.',
            ],
            'one part fewer' => [
                self::INTENT,
                $fewerParts,
                'A dropped part must change the key.',
            ],
        ];
    }

    /**
     * Scenario: the key carries no trace of what it was built from
     *   Given parts holding an order key and shopper-identifying values
     *   When the key is built
     *   Then none of those raw values appears anywhere in it
     *
     * @covers \Mollie\WooCommerce\Core\Security\IdempotencyKey::for
     */
    public function testNeverRevealsARawPartValue(): void
    {
        $secrets = [
            'order_key' => 'wc_order_CANARY7f3aKey',
            'email' => 'shopper.CANARY7f3a@example.org',
            'reference' => 'CANARY7f3a-express-ref',
        ];

        $key = IdempotencyKey::for('express.order.v1', $secrets + ['order' => 4711]);

        foreach ($secrets as $name => $value) {
            self::assertStringNotContainsString(
                $value,
                $key,
                sprintf('The raw value of "%s" reached the idempotency key.', $name)
            );
        }
        self::assertStringNotContainsString('CANARY7f3a', $key);
        self::assertRegExp(self::KEY_SHAPE, $key);
    }
}
