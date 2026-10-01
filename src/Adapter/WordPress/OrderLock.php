<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WordPress;

use wpdb;

/**
 * A short lock per order (or per express reference), so two requests cannot decide on the same
 * stale facts (blueprint ADR-007).
 *
 * MySQL GET_LOCK() is used provisionally (open question 1): it needs no schema, behaves the same on
 * HPOS and posts, and is released when the connection dies. It is server-wide, so the name carries
 * the database and table prefix. Everything about the mechanism lives in this class.
 */
final class OrderLock
{
    private const TIMEOUT_SECONDS = 3;

    private wpdb $db;

    public function __construct(wpdb $db)
    {
        $this->db = $db;
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
        $name = self::lockName($orderKey);
        $taken = $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, %d)', $name, self::TIMEOUT_SECONDS));

        if ((string) $taken !== '1') {
            throw new OrderLockTimeout('The order is being processed by another request.');
        }

        try {
            return $work();
        } finally {
            $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }
}
