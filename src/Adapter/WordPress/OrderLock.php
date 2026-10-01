<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WordPress;

use Automattic\WooCommerce\Caches\OrderCache;
use InvalidArgumentException;
use Throwable;
use WC_Order;
use wpdb;

/**
 * A short lock per order (or per express reference), so two requests cannot decide on the same
 * stale facts.
 */
final class OrderLock
{
    private const TIMEOUT_SECONDS = 3;

    /**
     * How many withLock() calls of this instance are running, the outermost included.
     */
    private int $depth = 0;

    private ?bool $holdsSeveralLocks = null;

    /**
     * @param EventLog|null $log Where a lock timeout of withFreshOrder() is reported.
     */
    public function __construct(private wpdb $db, private ?EventLog $log = null)
    {
    }

    /**
     * The MySQL lock name for an order id or express reference. Public so a test can contend for it.
     */
    public static function lockName(string $orderKey): string
    {
        $scope = defined('DB_NAME') ? (string) DB_NAME : '';
        $prefix = isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) ? (string) $GLOBALS['wpdb']->prefix : '';

        // MySQL allows 64 characters.
        return 'mwc_order_' . substr(hash('sha256', $scope . '|' . $prefix . '|' . $orderKey), 0, 40);
    }

    /**
     * Runs the work while holding the lock, and releases it however the work ends.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     * @throws OrderLockTimeout When the lock was not free within the timeout; the work did not run.
     */
    public function withLock(string $orderKey, callable $work)
    {
        if ($this->depth > 0 && !$this->canHoldSeveralLocks()) {
            return $this->run($work);
        }

        $name = self::lockName($orderKey);
        $taken = $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, %d)', $name, self::TIMEOUT_SECONDS));

        if ((string) $taken !== '1') {
            throw new OrderLockTimeout('The order is being processed by another request.');
        }

        try {
            return $this->run($work);
        } finally {
            $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }

    /**
     * Runs the work on the order as stored, while holding the order's lock: another request may have
     * written it while this one waited, so a decision is made on this order and on nothing read before.
     *
     * @template T
     * @param callable(WC_Order): T $work
     * @return T
     * @throws OrderLockTimeout When the lock was not free within the timeout; the work did not run.
     * @throws InvalidArgumentException When the order no longer exists; the work did not run.
     */
    public function withFreshOrder(int $orderId, callable $work)
    {
        $started = microtime(true);

        try {
            return $this->withLock((string) $orderId, function () use ($orderId, $work) {
                return $work($this->freshOrder($orderId));
            });
        } catch (OrderLockTimeout $timeout) {
            $this->log?->warning('order.lock_timeout', [
                'order' => $orderId,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            throw $timeout;
        }
    }

    /**
     * The order as stored, not as this process last loaded it. WooCommerce keeps loaded orders in its
     * OrderCache, which another process's write does not reach, so the order is removed from it first.
     */
    private function freshOrder(int $orderId): WC_Order
    {
        $this->forgetCachedOrder($orderId);
        clean_post_cache($orderId);
        wp_cache_delete(WC_Order::generate_meta_cache_key($orderId, 'orders'), 'orders');

        $order = wc_get_order($orderId);
        $dataStore = $order instanceof WC_Order ? $order->get_data_store() : null;
        if ($dataStore !== null && is_callable([$dataStore, 'clear_cached_data'])) {
            $dataStore->clear_cached_data([$orderId]);
            $this->forgetCachedOrder($orderId);
            $order = wc_get_order($orderId);
        }
        if (!$order instanceof WC_Order) {
            throw new InvalidArgumentException('The order no longer exists.');
        }

        return $order;
    }

    /**
     * WooCommerce's own API for its order object cache; a WooCommerce without it has nothing to forget.
     */
    private function forgetCachedOrder(int $orderId): void
    {
        if (!function_exists('wc_get_container') || !method_exists(OrderCache::class, 'remove')) {
            return;
        }
        try {
            wc_get_container()->get(OrderCache::class)->remove($orderId);
        } catch (Throwable $unavailable) {
            return;
        }
    }

    /**
     * @return mixed What the work returns.
     */
    private function run(callable $work)
    {
        $this->depth++;
        try {
            return $work();
        } finally {
            $this->depth--;
        }
    }

    /**
     * MySQL 5.7.5 or MariaDB 10.0.2 and later, asked once. MariaDB may report itself behind the
     * "5.5.5-" replication prefix.
     */
    private function canHoldSeveralLocks(): bool
    {
        if ($this->holdsSeveralLocks === null) {
            $version = (string) $this->db->get_var('SELECT VERSION()');
            if (preg_match('/(\d+\.\d+\.\d+)-MariaDB/i', $version, $mariaDb) === 1) {
                $this->holdsSeveralLocks = version_compare($mariaDb[1], '10.0.2', '>=');
            } else {
                preg_match('/^\d+\.\d+\.\d+/', $version, $mysql);
                $this->holdsSeveralLocks = version_compare($mysql[0] ?? '0', '5.7.5', '>=');
            }
        }

        return $this->holdsSeveralLocks;
    }
}
