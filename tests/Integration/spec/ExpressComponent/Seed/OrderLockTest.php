<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Seed;

use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use wpdb;

/**
 * The per-order lock when a locked operation takes a second lock (ADR-007, REQ-B5).
 *
 * StartExpressOrder holds the lock of an express_ref while EffectInterpreter stamps the new order
 * under the order's own lock. MySQL 5.7.5 and MariaDB 10.0.2 can hold both; before them, taking a
 * second named lock silently releases the first, which would let a second submit create a second
 * order. On such a server the nested work runs under the lock already held.
 *
 * @group integration
 * @group ExpressComponent
 * @covers \Mollie\WooCommerce\Adapter\WordPress\OrderLock
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
