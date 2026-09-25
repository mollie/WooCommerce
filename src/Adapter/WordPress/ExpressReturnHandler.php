<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WordPress;

use Mollie\WooCommerce\Adapter\WooCommerce\ExpressOrderFactsBuilder;
use WC_Order;

/**
 * wc-api/mollie_express_return?ref=…: where Mollie sends the shopper after the wallet.
 *
 * The session's redirectUrl was fixed before any order existed, so it carries the express_ref. This
 * handler only decides where the shopper goes, and never changes an order: the webhook does that.
 * The ref is a capability: its shape is checked, it is compared in constant time with the order's,
 * and an unknown ref gets the same answer as a failed payment. The order-received page enforces its
 * own order key. Mollie is not asked anything.
 */
class ExpressReturnHandler
{
    private const REF_SHAPE = '/^exr_[a-f0-9]{32}$/';

    public function __construct(
        private ExpressOrderFactsBuilder $orderFacts,
        private EventLog $log
    ) {
    }

    public function handle(): void
    {
        $order = $this->order($this->refFromRequest());

        if ($order instanceof WC_Order && !$order->has_status(['failed', 'cancelled'])) {
            $this->log->info('express.return.shown', ['order' => $order->get_id(), 'status' => $order->get_status()]);
            $this->redirect($order->get_checkout_order_received_url());

            return;
        }

        $this->log->info('express.return.shown', [
            'order' => $order instanceof WC_Order ? $order->get_id() : 0,
            'status' => $order instanceof WC_Order ? $order->get_status() : 'unknown',
        ]);
        wc_add_notice(
            __('Your express checkout payment was not completed. Please try again, or choose another payment method.', 'mollie-payments-for-woocommerce'),
            'error'
        );
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
