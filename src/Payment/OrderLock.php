<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Automattic\WooCommerce\Caches\OrderCache;
use InvalidArgumentException;
use Mollie\WooCommerce\Log\EventLog;
use Throwable;
use WC_Order;
use wpdb;

/**
 * Per-order (or express reference) lock, so two requests cannot decide on the same stale facts.
 */
final class OrderLock
{
    private const TIMEOUT_SECONDS = 3;

    private int $nestedRunDepth = 0;

    private ?bool $holdsSeveralLocks = null;

    public function __construct(private wpdb $db, private ?EventLog $log = null)
    {
    }

    public static function lockName(string $orderKey): string
    {
        $scope = defined('DB_NAME') ? (string) DB_NAME : '';
        $prefix = isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) ? (string) $GLOBALS['wpdb']->prefix : '';

        // Hashed: MySQL lock names are limited to 64 characters.
        return 'mwc_order_' . substr(hash('sha256', $scope . '|' . $prefix . '|' . $orderKey), 0, 40);
    }

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     * @throws OrderLockTimeout
     */
    public function withLock(string $orderKey, callable $work)
    {
        // Older servers release the held lock on a second GET_LOCK, so nested work runs under the held one.
        if ($this->nestedRunDepth > 0 && !$this->canHoldSeveralLocks()) {
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
     * Another request may have written the order while this one waited, so it is read after locking.
     *
     * @template T
     * @param callable(WC_Order): T $work
     * @return T
     * @throws OrderLockTimeout
     * @throws InvalidArgumentException When the order no longer exists.
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
     * Another process's write does not reach this process's OrderCache, so the order is evicted first.
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

    private function run(callable $work)
    {
        $this->nestedRunDepth++;
        try {
            return $work();
        } finally {
            $this->nestedRunDepth--;
        }
    }

    /**
     * MySQL >= 5.7.5 or MariaDB >= 10.0.2. MariaDB may report itself behind a "5.5.5-" prefix.
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
