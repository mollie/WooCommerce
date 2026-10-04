<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\Log\EventLog;
use WC_Order;
/**
 * Only redirects the returning shopper; the webhook changes the order.
 */
class ExpressReturnHandler
{
    private const REF_SHAPE = '/^exr_[a-f0-9]{32}$/';
    public function __construct(private ExpressOrderFactsBuilder $orderFacts, private EventLog $log)
    {
    }
    public function handle(): void
    {
        $order = $this->order($this->refFromRequest());
        if ($order instanceof WC_Order && !$order->has_status(['failed', 'cancelled'])) {
            $this->log->info('express.return.shown', ['order' => $order->get_id(), 'status' => $order->get_status()]);
            $this->redirect($order->get_checkout_order_received_url());
            return;
        }
        $this->log->info('express.return.shown', ['order' => $order instanceof WC_Order ? $order->get_id() : 0, 'status' => $order instanceof WC_Order ? $order->get_status() : 'unknown']);
        wc_add_notice(__('Your express checkout payment was not completed. Please try again, or choose another payment method.', 'mollie-payments-for-woocommerce'), 'error');
        $this->redirect(wc_get_checkout_url());
    }
    private function refFromRequest(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a return from Mollie carries no nonce; the ref is the capability, checked below.
        $ref = isset($_GET['ref']) ? sanitize_text_field(wp_unslash((string) $_GET['ref'])) : '';
        return preg_match(self::REF_SHAPE, $ref) === 1 ? $ref : '';
    }
    private function order(string $ref): ?WC_Order
    {
        return $ref === '' ? null : $this->orderFacts->orderByRef($ref);
    }
    private function redirect(string $url): void
    {
        wp_safe_redirect($url);
        exit;
    }
}
