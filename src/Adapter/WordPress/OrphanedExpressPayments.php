<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WordPress;

/**
 * Remembers express payments that were taken but belong to no order, and tells the shop manager.
 *
 * With `event.defer()` in place a refused submit aborts the payment at Mollie, so nothing should
 * ever land here.
 *
 * A webhook is not an admin request, so the notice cannot be shown where it is discovered: the ids
 * are kept in one option and rendered the next time an administrator loads a page. Only ids and
 * amounts are stored — never an address, a name or an email.
 */
class OrphanedExpressPayments
{
    public const OPTION = 'mollie_express_orphaned_payments';
    /** Enough to notice a pattern, few enough never to grow an option unbounded. */
    private const KEEP = 20;
    /**
     * @param string $paymentId The Mollie payment that was taken.
     * @param string $reason Why no order matched, as the event catalogue names it.
     */
    public function remember(string $paymentId, string $reason, string $amount, string $currency): void
    {
        $known = $this->all();
        if (isset($known[$paymentId])) {
            return;
        }
        $known[$paymentId] = ['reason' => $reason, 'amount' => $amount, 'currency' => $currency, 'seenAt' => gmdate('c')];
        if (count($known) > self::KEEP) {
            $known = array_slice($known, -self::KEEP, null, \true);
        }
        update_option(self::OPTION, $known, \false);
    }
    /**
     * @return array<string, array{reason: string, amount: string, currency: string, seenAt: string}>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? $stored : [];
    }
    public function forget(): void
    {
        delete_option(self::OPTION);
    }
    /**
     * The notice, for an administrator who can act on it. Dismissing it clears the list, because the
     * payments themselves live at Mollie: this is a pointer, not a record.
     */
    public function renderNotice(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $orphaned = $this->all();
        if ($orphaned === []) {
            return;
        }
        if (isset($_GET['mollie_express_dismiss_orphaned'])) {
            // phpcs:ignore WordPress.Security.NonceVerification
            if (wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), self::OPTION) !== \false) {
                $this->forget();
                return;
            }
        }
        $lines = [];
        foreach ($orphaned as $paymentId => $details) {
            $lines[] = sprintf('%s — %s %s (%s)', esc_html($paymentId), esc_html($details['amount']), esc_html($details['currency']), esc_html($details['reason']));
        }
        printf('<div class="notice notice-error"><p><strong>%s</strong></p><p>%s</p><ul><li>%s</li></ul><p><a href="%s">%s</a></p></div>', esc_html__('Mollie express checkout: a payment was taken without an order', 'mollie-payments-for-woocommerce'), esc_html__('Mollie holds these express payments, and no order in this store represents them. Check each one in your Mollie dashboard and either refund it or create the order by hand.', 'mollie-payments-for-woocommerce'), implode('</li><li>', $lines), esc_url(wp_nonce_url(add_query_arg('mollie_express_dismiss_orphaned', '1'), self::OPTION)), esc_html__('I have dealt with these', 'mollie-payments-for-woocommerce'));
    }
}
