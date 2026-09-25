<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WordPress;

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
    public function __construct(private wpdb $db)
    {
    }
    /**
     * The MySQL lock name for an order id or express reference. Public so a test can contend for it.
     */
    public static function lockName(string $orderKey): string
    {
        $scope = defined('DB_NAME') ? (string) \DB_NAME : '';
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
            throw new \Mollie\WooCommerce\Adapter\WordPress\OrderLockTimeout('The order is being processed by another request.');
        }
        try {
            return $this->run($work);
        } finally {
            $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $name));
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
