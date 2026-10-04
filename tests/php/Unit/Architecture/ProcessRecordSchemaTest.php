<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Architecture;

use InvalidArgumentException;
use Mollie\WooCommerce\Payment\Rules\Values\ProcessRecord;
use Mollie\WooCommerceTests\TestCase;

/**
 * @covers \Mollie\WooCommerce\Payment\Rules\Values\ProcessRecord
 */
class ProcessRecordSchemaTest extends TestCase
{
    private const ALLOWLIST = [
        'version' => [],
        'attempts' => ['id', 'origin', 'supersededBy'],
        'processed' => [],
        'inFlight' => ['kind', 'idempotencyKey', 'amount', 'by'],
        'open' => ['question', 'mollieId'],
        'cancelledBy' => [],
    ];

    private const AMOUNT_FIELDS = ['value', 'currency'];

    /**
     * Scenario: a record with anything outside the allowlist cannot be built
     *   Given a stored record with one section or field too many
     *   When it is built
     *   Then it fails
     *
     * @dataProvider outsideTheAllowlist
     */
    public function testRefusesARecordWithASectionOrFieldOutsideTheAllowlist(array $stored): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProcessRecord::fromArray($stored);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public function outsideTheAllowlist(): array
    {
        $full = self::fullRecord();

        return [
            'an unknown section' => [['notes' => []] + $full],
            'a payment status, which Mollie knows' => [['status' => 'paid'] + $full],
            'an order amount, which is no command\'s intent' => [['amount' => ['value' => '49.90', 'currency' => 'EUR']] + $full],
            'an attempt carrying its payment status' => [self::withEntry($full, 'attempts', ['status' => 'paid'])],
            'an attempt carrying an amount' => [self::withEntry($full, 'attempts', ['amount' => '49.90'])],
            'a command in flight carrying an unknown field' => [self::withEntry($full, 'inFlight', ['refunded' => '10.00'])],
            'a command amount carrying an unknown field' => [self::withAmount($full, ['settlement' => '49.00'])],
            'an open question carrying an unknown field' => [self::withEntry($full, 'open', ['email' => 'a@example.com'])],
            'a processed entry that is not a string' => [['processed' => [['id' => 'tr_1']]] + $full],
            'a processed entry that is empty' => [['processed' => ['']] + $full],
            'a canceller outside the list' => [['cancelledBy' => 'customer'] + $full],
            'a section that is not a list' => [['attempts' => 'tr_1'] + $full],
        ];
    }

    /**
     * Scenario: a full record has exactly the allowed sections and fields
     *   Given every section filled
     *   When it is turned into an array
     *   Then its keys are the allowlist
     */
    public function testAFullRecordHasExactlyTheAllowedSectionsAndFields(): void
    {
        $stored = ProcessRecord::fromArray(self::fullRecord())->toArray();

        self::assertSame(array_keys(self::ALLOWLIST), array_keys($stored), 'R-25: the record\'s sections are the allowlist, in its order.');
        foreach (self::ALLOWLIST as $section => $fields) {
            if ($fields === []) {
                continue;
            }
            self::assertNotEmpty($stored[$section], "The full record must fill {$section}, or this test proves nothing.");
            foreach ($stored[$section] as $entry) {
                self::assertSame($fields, array_keys($entry), "R-25: the fields of {$section} are the allowlist.");
            }
        }
        self::assertSame(self::AMOUNT_FIELDS, array_keys($stored['inFlight'][0]['amount']));
    }

    /**
     * @return array<string, mixed>
     */
    private static function fullRecord(): array
    {
        return [
            'version' => 1,
            'attempts' => [['id' => 'tr_1', 'origin' => 'express_session:ses_9', 'supersededBy' => 'tr_2']],
            'processed' => ['tr_1:canceled', 're_4qqh'],
            'inFlight' => [[
                'kind' => 'capture',
                'idempotencyKey' => 'capture-tr_1-1',
                'amount' => ['value' => '49.90', 'currency' => 'EUR'],
                'by' => 'merchant',
            ]],
            'open' => [['question' => 'paid_after_merchant_cancel', 'mollieId' => 'tr_3']],
            'cancelledBy' => 'cleanup',
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function withEntry(array $record, string $section, array $extra): array
    {
        $record[$section][0] = $record[$section][0] + $extra;

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function withAmount(array $record, array $extra): array
    {
        $record['inFlight'][0]['amount'] = $record['inFlight'][0]['amount'] + $extra;

        return $record;
    }
}
