<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

/**
 * Paid express payments with no order. Webhooks cannot show admin notices, so they are stored
 * and shown on the next admin page load. Ids and amounts only, no personal data.
 */
class OrphanedExpressPayments
{
    public const OPTION = 'mollie_express_orphaned_payments';
    private const MAX_REMEMBERED = 20;
    public function remember(string $paymentId, string $reason, string $amount, string $currency): void
    {
        $known = $this->all();
        if (isset($known[$paymentId])) {
            return;
        }
        $known[$paymentId] = ['reason' => $reason, 'amount' => $amount, 'currency' => $currency, 'seenAt' => gmdate('c')];
        if (count($known) > self::MAX_REMEMBERED) {
            $known = array_slice($known, -self::MAX_REMEMBERED, null, \true);
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
    /** Dismissing clears the list: the payments live at Mollie, this is only a pointer. */
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
