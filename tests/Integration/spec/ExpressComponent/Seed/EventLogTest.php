<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Seed;

use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;

/**
 * The only way new code may log (blueprint chokepoint 4, ADR-014).
 *
 * 231 of 237 log calls today are debug lines behind one switch, and what they write includes whole
 * Mollie objects, the webhook URL with its secret and the return URL with the order key (S-01,
 * S-05). The answer is not to mask those values one by one — a blocklist fails the first time
 * somebody logs a new object. It is an allowlist: the fields in docs/architecture/events.md are
 * the fields that may be written, and anything else is dropped rather than masked.
 *
 * The correlation id is the other half. Without it the story of one order has to be reassembled by
 * timestamp; with it, support reads one cid top to bottom and the event names say how far the
 * request got. So it has to be the same for every event of a request, and different for the next.
 *
 * @covers \Mollie\WooCommerce\Adapter\WordPress\EventLog
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressSeed
 */
class EventLogTest extends ExpressFlowTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // The strictest case for a leak check: the merchant's debug switch is on.
        $this->setOptionForTest('mollie-payments-for-woocommerce_debug', 'yes');
    }

    /**
     * Scenario: an event is written with its name and its allowlisted fields
     *   Given the effects.applied event of the catalogue
     *   When it is logged with cid, order, status and ms
     *   Then the record names the event and carries exactly those fields
     *
     * @test
     */
    public function it_writes_an_event_with_its_name_and_allowlisted_fields_only(): void
    {
        $log = $this->eventLog();

        $log->info('effects.applied', ['order' => 4711, 'status' => 'processing', 'ms' => 12]);

        $record = $this->lastRecord();
        $this->assertSame('info', $record['level']);
        $this->assertStringContainsString('effects.applied', $record['message'], 'Event names are the API people search for.');

        $context = $record['context'];
        $this->assertSame(4711, $context['order']);
        $this->assertSame('processing', $context['status']);
        $this->assertSame(12, $context['ms']);
        $this->assertArrayHasKey('cid', $context);
        $this->assertSame(
            ['cid', 'ms', 'order', 'status'],
            $this->sortedKeys($context),
            'An event carries its allowlisted fields and nothing else.'
        );
    }

    /**
     * Scenario: a field that is not on the allowlist is dropped, not masked
     *   Given an event logged with an email and a checkout URL alongside its real fields
     *   When it is written
     *   Then those fields are absent from the record entirely
     *   And no marked secret or personal detail can be found anywhere in the log
     *
     * @test
     */
    public function it_drops_a_field_that_is_not_on_the_allowlist(): void
    {
        $log = $this->eventLog();

        $log->info('effects.applied', [
            'order' => 4711,
            'status' => 'processing',
            'ms' => 12,
            'email' => CanaryData::EMAIL,
            'checkout_url' => 'https://shop.example/checkout?key=' . CanaryData::WEBHOOK_SECRET,
            'billingAddress' => CanaryData::mollieAddress(),
        ]);

        $context = $this->lastRecord()['context'];
        $this->assertArrayNotHasKey('email', $context, 'An unknown field is dropped, not masked.');
        $this->assertArrayNotHasKey('checkout_url', $context);
        $this->assertArrayNotHasKey('billingAddress', $context);
        $this->assertSame(['cid', 'ms', 'order', 'status'], $this->sortedKeys($context));

        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: one request, one correlation id; the next request, another
     *   Given three events written during one request
     *   Then all three carry the same cid
     *   When a second request logs its own event
     *   Then its cid differs, so two requests never read as one story
     *
     * @test
     */
    public function it_gives_every_event_of_one_request_the_same_correlation_id_and_a_new_one_to_the_next(): void
    {
        $first = $this->eventLog();
        $first->info('express.session.created', ['session' => 'sess_one', 'surface' => 'checkout']);
        $first->warning('express.order.refused', ['session' => 'sess_one', 'reason' => 'cart_changed']);
        $first->info('effects.applied', ['order' => 4711, 'status' => 'processing', 'ms' => 3]);

        $firstCids = $this->correlationIds();
        $this->assertCount(1, $firstCids, 'Every event of one request must carry one cid.');

        // A second boot is a second request: a new container, a new event log, a new id.
        $second = $this->eventLog();
        $second->info('express.session.created', ['session' => 'sess_two', 'surface' => 'checkout']);

        $this->assertCount(2, $this->correlationIds(), 'A second request must not reuse the first cid.');
        $this->assertNotContains($this->lastRecord()['context']['cid'], $firstCids);
    }

    /**
     * Scenario: the very first event of a request already has its correlation id
     *   Given a request that has logged nothing yet, so no id has been generated
     *   When its first event is written
     *   Then the cid is present, non-empty and well formed
     *
     * The boundary matters because the id is generated lazily and read by every call site. An id
     * that is only created on the second event would leave the first line of every request — often
     * the admission or refusal line, the one support looks for — with an empty cid that still
     * passes an assertArrayHasKey.
     *
     * @test
     */
    public function it_generates_the_correlation_id_for_the_very_first_event_of_a_request(): void
    {
        $log = $this->eventLog();
        $this->assertSame([], $this->logger()->records(), 'This request must not have logged yet.');

        $log->warning('express.session.refused', ['surface' => 'checkout', 'reason' => 'shipping_incomplete']);

        $cid = $this->lastRecord()['context']['cid'] ?? null;
        $this->assertIsString($cid, 'The first event of a request has no cid at all.');
        $this->assertNotSame('', trim((string) $cid), 'The first event of a request carries an empty cid.');
        $this->assertRegExp(
            '/^[A-Za-z0-9._\-]{8,64}$/',
            (string) $cid,
            'A cid has to be something a human can copy out of a log and search for.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function eventLog(): EventLog
    {
        $log = $this->bootExpress()->get(EventLog::class);
        $this->assertInstanceOf(EventLog::class, $log);

        return $log;
    }

    /**
     * @return array{level: string, message: string, context: array<mixed>}
     */
    private function lastRecord(): array
    {
        $records = $this->logger()->records();
        $this->assertNotEmpty($records, 'The event was not logged at all.');

        return $records[count($records) - 1];
    }

    /**
     * Every distinct correlation id seen so far.
     *
     * @return array<int, string>
     */
    private function correlationIds(): array
    {
        $cids = [];
        foreach ($this->logger()->records() as $record) {
            if (isset($record['context']['cid'])) {
                $cids[] = (string) $record['context']['cid'];
            }
        }

        return array_values(array_unique($cids));
    }

    /**
     * @param array<mixed> $context
     * @return array<int, string>
     */
    private function sortedKeys(array $context): array
    {
        $keys = array_map('strval', array_keys($context));
        sort($keys);

        return $keys;
    }
}
