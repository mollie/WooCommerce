<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Seed;

use Automattic\WooCommerce\Utilities\OrderUtil;
use InvalidArgumentException;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\Payment\OrderLockTimeout;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use WC_Order;
use wpdb;

/**
 * The per-order lock: nested locks, fresh reads, and a lock held elsewhere.
 * Before MySQL 5.7.5 / MariaDB 10.0.2 a second named lock releases the first, so nested work runs under the held one.
 *
 * @group integration
 * @group ExpressComponent
 * @covers \Mollie\WooCommerce\Payment\OrderLock
 */
class OrderLockTest extends ExpressFlowTestCase
{
    /**
     * Scenario: a nested lock keeps the outer one on a server that can hold several
     *   Given the site's own database
     *   When work under one lock takes a second lock
     *   Then the first lock is still held inside the second
     *   And both are released afterwards
     *
     * @test
     */
    public function it_keeps_the_outer_lock_while_a_nested_one_is_held(): void
    {
        global $wpdb;
        $lock = new OrderLock($wpdb);
        $outer = 'exr_outer_' . uniqid();
        $isHeld = static function (string $key) use ($wpdb): bool {
            return $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', OrderLock::lockName($key))) !== null;
        };

        $heldInside = $lock->withLock($outer, static function () use ($lock, $outer, $isHeld): bool {
            return $lock->withLock('inner_' . uniqid(), static function () use ($outer, $isHeld): bool {
                return $isHeld($outer);
            });
        });

        $this->assertTrue($heldInside, 'Taking the nested lock released the outer one.');
        $this->assertFalse($isHeld($outer), 'The outer lock must be released afterwards.');
    }

    /**
     * Scenario: a server that holds one named lock at a time never takes a second one
     *   Given a database reporting MySQL 5.6
     *   When work under one lock takes a second lock
     *   Then GET_LOCK is asked once, for the outer lock only
     *   And the nested work still runs and answers
     *
     * @test
     * @dataProvider singleLockServers
     */
    public function it_runs_nested_work_under_the_held_lock_where_only_one_can_be_held(string $version): void
    {
        $db = $this->databaseReporting($version);
        $lock = new OrderLock($db);

        $answer = $lock->withLock('outer', static function () use ($lock): string {
            return $lock->withLock('inner', static function (): string {
                return 'nested work ran';
            });
        });

        $this->assertSame('nested work ran', $answer);
        $this->assertSame([OrderLock::lockName('outer')], $db->locksTaken);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function singleLockServers(): array
    {
        return [
            'MySQL 5.6' => ['5.6.51-log'],
            'MariaDB 5.5 behind the replication prefix' => ['5.5.5-5.5.68-MariaDB'],
        ];
    }

    /**
     * @test
     * @dataProvider severalLockServers
     */
    public function it_takes_both_locks_where_several_can_be_held(string $version): void
    {
        $db = $this->databaseReporting($version);
        $lock = new OrderLock($db);

        $lock->withLock('outer', static function () use ($lock): void {
            $lock->withLock('inner', static function (): void {
            });
        });

        $this->assertSame([OrderLock::lockName('outer'), OrderLock::lockName('inner')], $db->locksTaken);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function severalLockServers(): array
    {
        return [
            'MySQL 5.7.5' => ['5.7.5'],
            'MySQL 8' => ['8.0.36'],
            'MariaDB 10.0.2 behind the replication prefix' => ['5.5.5-10.0.2-MariaDB'],
            'MariaDB 11' => ['11.8.6-MariaDB-ubu2404-log'],
        ];
    }

    /**
     * Scenario: an order another process changed is read as changed inside the lock
     *   Given an order this process has already loaded, so WooCommerce holds it in its OrderCache
     *   And another process changes its status and adds a meta row directly in the order tables
     *   When the order is read through OrderLock::withFreshOrder()
     *   Then the work receives the order with the new status
     *   And with the new meta row
     *
     * No wp_cache_flush() and no `new WC_Order()`: either would hide a stale OrderCache read.
     *
     * @test
     */
    public function it_reads_an_order_another_process_changed_through_with_fresh_order(): void
    {
        global $wpdb;
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $orderId = $order->get_id();
        $this->assertSame('pending', wc_get_order($orderId)->get_status(), 'The order must be loaded, and cached, by this process first.');

        $this->writeAsAnotherProcess($orderId, 'wc-processing', '_mollie_outside_write', 'written-elsewhere');

        $seen = null;
        (new OrderLock($wpdb))->withFreshOrder($orderId, static function (WC_Order $fresh) use (&$seen): void {
            $seen = [
                'status' => $fresh->get_status(),
                'meta' => (string) $fresh->get_meta('_mollie_outside_write'),
            ];
        });

        $this->assertSame(
            ['status' => 'processing', 'meta' => 'written-elsewhere'],
            $seen,
            'The order read inside the lock is the one this process cached, not the one stored.'
        );
    }

    /**
     * Scenario: nothing runs while another request holds the order lock
     *   Given a second database connection holding this order's lock
     *   When OrderLock::withFreshOrder() is asked for that order
     *   Then it throws the retryable OrderLockTimeout
     *   And the work never ran
     *   And the order is exactly as it was
     *
     * @test
     */
    public function it_runs_no_work_and_throws_when_the_order_lock_is_held_elsewhere(): void
    {
        global $wpdb;
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $orderId = $order->get_id();
        $holder = $this->holdTheLockElsewhere((string) $orderId);
        $ran = false;
        $thrown = null;

        try {
            (new OrderLock($wpdb))->withFreshOrder($orderId, static function (WC_Order $fresh) use (&$ran): void {
                $ran = true;
                $fresh->update_meta_data('_mollie_payment_id', 'tr_shouldNotBeWritten');
                $fresh->save();
            });
        } catch (OrderLockTimeout $timeout) {
            $thrown = $timeout;
        } finally {
            $this->releaseTheLock($holder, (string) $orderId);
        }

        $this->assertInstanceOf(OrderLockTimeout::class, $thrown, 'A lock it cannot take must be a retryable failure, not a silent skip.');
        $this->assertFalse($ran, 'The work must not run without the lock.');
        $after = wc_get_order($orderId);
        $this->assertSame('pending', $after->get_status());
        $this->assertSame('', (string) $after->get_meta('_mollie_payment_id'));
    }

    /**
     * Scenario: an order that no longer exists is refused before any work runs
     *   Given an order that was deleted
     *   When OrderLock::withFreshOrder() is asked for it
     *   Then it throws InvalidArgumentException
     *   And the work never ran
     *
     * @test
     */
    public function it_throws_and_runs_no_work_when_the_order_no_longer_exists(): void
    {
        global $wpdb;
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $orderId = $order->get_id();
        $order->delete(true);
        $ran = false;

        $this->expectException(InvalidArgumentException::class);
        try {
            (new OrderLock($wpdb))->withFreshOrder($orderId, static function () use (&$ran): void {
                $ran = true;
            });
        } finally {
            $this->assertFalse($ran, 'The work must not run for an order that does not exist.');
        }
    }

    /**
     * Another process's write: rows changed directly, no WooCommerce hook, no local cache touched.
     */
    private function writeAsAnotherProcess(int $orderId, string $status, string $metaKey, string $metaValue): void
    {
        global $wpdb;
        $hpos = OrderUtil::custom_orders_table_usage_is_enabled();
        $ordersTable = OrderUtil::get_table_for_orders();
        $metaTable = OrderUtil::get_table_for_order_meta();

        $updated = $wpdb->update(
            $ordersTable,
            [$hpos ? 'status' : 'post_status' => $status],
            [$hpos ? 'id' : 'ID' => $orderId]
        );
        $inserted = $wpdb->insert(
            $metaTable,
            [$hpos ? 'order_id' : 'post_id' => $orderId, 'meta_key' => $metaKey, 'meta_value' => $metaValue]
        );

        $this->assertSame(1, $updated, 'The outside write did not change the order row.');
        $this->assertSame(1, $inserted, 'The outside write did not add the meta row.');
    }

    /**
     * A second connection, because MySQL grants a session the lock it already holds.
     */
    private function holdTheLockElsewhere(string $orderKey): wpdb
    {
        $holder = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $taken = $holder->get_var($holder->prepare('SELECT GET_LOCK(%s, 0)', OrderLock::lockName($orderKey)));
        $this->assertSame('1', (string) $taken, 'The test could not take the lock it needs to hold.');

        return $holder;
    }

    private function releaseTheLock(wpdb $holder, string $orderKey): void
    {
        $holder->get_var($holder->prepare('SELECT RELEASE_LOCK(%s)', OrderLock::lockName($orderKey)));
        $holder->close();
    }

    private function databaseReporting(string $version): wpdb
    {
        return new class ($version) extends wpdb {
            /** @var list<string> */
            public array $locksTaken = [];

            private string $version;

            // phpcs:ignore -- a stand-in that never connects.
            public function __construct(string $version)
            {
                $this->version = $version;
            }

            public function prepare($query, ...$args)
            {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query = null, $x = 0, $y = 0)
            {
                if ($query === 'SELECT VERSION()') {
                    return $this->version;
                }
                if (preg_match("/^SELECT GET_LOCK\\('([^']+)'/", (string) $query, $matches) === 1) {
                    $this->locksTaken[] = $matches[1];
                }

                return '1';
            }
        };
    }
}
