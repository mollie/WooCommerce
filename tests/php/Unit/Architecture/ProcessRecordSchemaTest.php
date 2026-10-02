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
        'processed' => [],
        'open' => ['question', 'mollieId'],
        'cancelledBy' => [],
    ];

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
            // Sections of practice P5 that nothing writes yet: they join the allowlist with their first writer.
            'attempts, which nothing writes yet' => [['attempts' => []] + $full],
            'commands in flight, which nothing writes yet' => [['inFlight' => []] + $full],
            'an open question carrying an unknown field' => [self::withEntry($full, 'open', ['email' => 'a@example.com'])],
            'an open question carrying a payment status' => [self::withEntry($full, 'open', ['status' => 'paid'])],
            'a processed entry that is not a string' => [['processed' => [['id' => 'tr_1']]] + $full],
            'a processed entry that is empty' => [['processed' => ['']] + $full],
            'a canceller outside the list' => [['cancelledBy' => 'customer'] + $full],
            'a section that is not a list' => [['processed' => 'tr_1'] + $full],
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
    }

    /**
     * @return array<string, mixed>
     */
    private static function fullRecord(): array
    {
        return [
            'version' => 1,
            'processed' => ['tr_1:canceled', 're_4qqh'],
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
}
