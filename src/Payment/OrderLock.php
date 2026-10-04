<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Automattic\WooCommerce\Caches\OrderCache;
use InvalidArgumentException;
use Mollie\WooCommerce\Log\EventLog;
use Throwable;
use WC_DateTime;
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
    /**
     * @var callable(): int
     */
    private $countMollieCalls;
    /**
     * @param (callable(): int)|null $countMollieCalls
     */
    public function __construct(private wpdb $db, private ?EventLog $log = null, ?callable $countMollieCalls = null)
    {
        $this->countMollieCalls = $countMollieCalls ?? static fn(): int => 0;
    }
    public static function lockName(string $orderKey): string
    {
        $scope = defined('DB_NAME') ? (string) \DB_NAME : '';
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
            throw new \Mollie\WooCommerce\Payment\OrderLockTimeout('The order is being processed by another request.');
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
        $started = microtime(\true);
        $takenAt = null;
        try {
            return $this->withLock((string) $orderId, function () use ($orderId, $work, $started, &$takenAt) {
                $takenAt = microtime(\true);
                $this->log?->step('lock.taken', ['order' => $orderId, 'wait_ms' => $this->msBetween($started, $takenAt)]);
                return $this->runObserved($orderId, $work);
            });
        } catch (\Mollie\WooCommerce\Payment\OrderLockTimeout $timeout) {
            $this->log?->warning('order.lock_timeout', ['order' => $orderId, 'ms' => $this->msBetween($started, microtime(\true))]);
            throw $timeout;
        } finally {
            if ($takenAt !== null) {
                $this->log?->step('lock.released', ['order' => $orderId, 'held_ms' => $this->msBetween($takenAt, microtime(\true))]);
            }
        }
    }
    /**
     * @template T
     * @param callable(WC_Order): T $work
     * @return T
     */
    private function runObserved(int $orderId, callable $work)
    {
        $order = $this->freshOrder($orderId);
        if ($this->log === null) {
            return $work($order);
        }
        // Collected only when order.written will be written.
        $before = $this->log->writesInfo() ? $this->snapshot($order) : null;
        $callsBefore = ($this->countMollieCalls)();
        $started = microtime(\true);
        $result = $work($order);
        $ms = $this->msBetween($started, microtime(\true));
        $statusAsked = $order->get_status();
        $mollieCalls = ($this->countMollieCalls)() - $callsBefore;
        try {
            $after = $this->freshOrder($orderId);
        } catch (InvalidArgumentException $deleted) {
            return $result;
        }
        if ($before !== null) {
            $this->reportWrite($orderId, $before, $this->snapshot($after), $ms);
        }
        if ($after->get_status() !== $statusAsked || $mollieCalls > 0) {
            $this->log->warning('listeners.observed', ['order' => $orderId, 'status_asked' => $statusAsked, 'status_found' => $after->get_status(), 'mollie_calls' => $mollieCalls]);
        }
        return $result;
    }
    /**
     * @param array{status: string, meta: array<string, string>, data: string, notes: array<int, int>} $before
     * @param array{status: string, meta: array<string, string>, data: string, notes: array<int, int>} $after
     */
    private function reportWrite(int $orderId, array $before, array $after, int $ms): void
    {
        $metaKeys = [];
        foreach (array_unique(array_merge(array_keys($before['meta']), array_keys($after['meta']))) as $key) {
            if (($before['meta'][$key] ?? null) !== ($after['meta'][$key] ?? null)) {
                $metaKeys[] = (string) $key;
            }
        }
        sort($metaKeys);
        $noteKeys = array_values(array_diff($after['notes'], $before['notes']));
        if ($before['status'] === $after['status'] && $metaKeys === [] && $noteKeys === [] && $before['data'] === $after['data']) {
            return;
        }
        $this->log?->info('order.written', ['order' => $orderId, 'status_before' => $before['status'], 'status_after' => $after['status'], 'meta_keys' => implode(',', $metaKeys), 'note_keys' => implode(',', $noteKeys), 'ms' => $ms]);
    }
    /**
     * Hashes only, so no value can reach a log.
     *
     * @return array{status: string, meta: array<string, string>, data: string, notes: array<int, int>}
     */
    private function snapshot(WC_Order $order): array
    {
        $meta = [];
        foreach ($order->get_meta_data() as $item) {
            $data = $item->get_data();
            $key = (string) $data['key'];
            $meta[$key] = ($meta[$key] ?? '') . md5(serialize($data['value']));
        }
        $data = $order->get_data();
        foreach (['meta_data', 'date_modified', 'line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines'] as $ignored) {
            unset($data[$ignored]);
        }
        $notes = array_map(static fn($note): int => (int) $note->id, wc_get_order_notes(['order_id' => $order->get_id()]));
        sort($notes);
        return ['status' => $order->get_status(), 'meta' => $meta, 'data' => md5(serialize($this->plain($data))), 'notes' => $notes];
    }
    /**
     * @param mixed $value
     * @return mixed
     */
    private function plain($value)
    {
        if ($value instanceof WC_DateTime) {
            return $value->getTimestamp();
        }
        if (is_array($value)) {
            return array_map([$this, 'plain'], $value);
        }
        return is_scalar($value) || $value === null ? $value : null;
    }
    private function msBetween(float $from, float $to): int
    {
        return (int) round(($to - $from) * 1000);
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
