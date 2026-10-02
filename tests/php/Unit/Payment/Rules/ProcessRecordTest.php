<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Rules;

use InvalidArgumentException;
use Mollie\WooCommerce\Payment\Rules\Values\ProcessRecord;
use Mollie\WooCommerceTests\TestCase;

/**
 * @covers \Mollie\WooCommerce\Payment\Rules\Values\ProcessRecord
 */
class ProcessRecordTest extends TestCase
{
    private const EMPTY = [
        'version' => 1,
        'processed' => [],
        'open' => [],
        'cancelledBy' => null,
    ];

    /**
     * Scenario: a record reads back as written
     *   Given a stored version 1 record
     *   When it is built and turned back into an array
     *   Then the array is the stored one
     *
     * @dataProvider storedRecords
     */
    public function testRoundTripsThroughFromArrayAndToArray(array $stored): void
    {
        $record = ProcessRecord::fromArray($stored);

        self::assertSame($stored, $record->toArray());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public function storedRecords(): array
    {
        return [
            'empty' => [self::EMPTY],
            'processed' => [array_replace(self::EMPTY, ['processed' => ['ses_9:expired', 'tr_1:canceled', 're_4qqh']])],
            'open' => [array_replace(self::EMPTY, ['open' => [['question' => 'paid_after_merchant_cancel', 'mollieId' => 'tr_3']]])],
            'cancelled by cleanup' => [array_replace(self::EMPTY, ['cancelledBy' => 'cleanup'])],
            'cancelled by the merchant' => [array_replace(self::EMPTY, ['cancelledBy' => 'merchant'])],
            'cancelled by a webhook' => [array_replace(self::EMPTY, ['cancelledBy' => 'webhook'])],
        ];
    }

    /**
     * Scenario: an order without a record has an empty one
     *   Given no record
     *   When the empty record is asked for
     *   Then every section is empty
     */
    public function testTheEmptyRecordHoldsNothing(): void
    {
        $record = ProcessRecord::empty();

        self::assertSame(self::EMPTY, $record->toArray());
        self::assertNull($record->cancelledBy());
        self::assertSame([], $record->processed());
    }

    /**
     * Scenario: a missing or older version reads as empty
     *   Given a filled record without a version, or of version 0
     *   When it is built
     *   Then it is empty
     *
     * @dataProvider olderVersions
     */
    public function testReadsAMissingOrOlderVersionAsAnEmptyRecord(array $stored): void
    {
        $record = ProcessRecord::fromArray($stored);

        self::assertSame(self::EMPTY, $record->toArray());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public function olderVersions(): array
    {
        $filled = ['processed' => ['tr_1:canceled'], 'cancelledBy' => 'cleanup'] + self::EMPTY;
        $unversioned = $filled;
        unset($unversioned['version']);

        return [
            'no version' => [$unversioned],
            'version 0' => [['version' => 0] + $filled],
        ];
    }

    /**
     * Scenario: a newer version is refused
     *   Given a stored record of version 2
     *   When it is built
     *   Then it fails
     */
    public function testRefusesANewerVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProcessRecord::fromArray(['version' => 2] + self::EMPTY);
    }

    /**
     * Scenario: with-methods return a copy
     *   Given an empty record
     *   When a canceller and a processed entry are added
     *   Then the copy holds them and the original is still empty
     */
    public function testWithMethodsReturnACopyAndLeaveTheOriginalUntouched(): void
    {
        $original = ProcessRecord::empty();

        $changed = $original->withCancelledBy('cleanup')->withProcessed('ses_9:expired');

        self::assertSame('cleanup', $changed->cancelledBy());
        self::assertSame(['ses_9:expired'], $changed->processed());
        self::assertSame(self::EMPTY, $original->toArray());
    }

    /**
     * Scenario: an event handled twice is recorded once
     *   Given a record that already holds tr_1:canceled
     *   When it is added again
     *   Then it is held once
     */
    public function testAddsAProcessedEntryOnlyOnce(): void
    {
        $record = ProcessRecord::empty()->withProcessed('tr_1:canceled');

        $again = $record->withProcessed('tr_1:canceled');

        self::assertSame(['tr_1:canceled'], $again->processed());
    }

    /**
     * Scenario: a canceller outside the list cannot be set
     *   Given an empty record
     *   When the canceller is set to customer
     *   Then it fails
     */
    public function testRefusesACancellerOutsideTheList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProcessRecord::empty()->withCancelledBy('customer');
    }
}
