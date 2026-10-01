<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Workflow;

use Mollie\WooCommerce\Adapter\Mollie\MollieApi;
use Mollie\WooCommerce\Adapter\WooCommerce\EffectInterpreter;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Core\Clock;
use Mollie\WooCommerce\Core\Express\AbandonDecision;
use Mollie\WooCommerce\Core\Types\ExpressSession;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;
use Throwable;
use WC_Order;

/**
 * Cancels express orders the shopper started and never finished, on the existing
 * action mollie_woocommerce_cancel_unpaid_orders.
 *
 * Only pending mollie_express orders whose session expired more than the grace period ago are looked
 * at. For each, Mollie is asked about the payment when the order knows one, else about the session,
 * and the order is cancelled only when Mollie positively says it can no longer be paid. When Mollie
 * cannot be reached the order is kept, and the next run tries again.
 */
final class ExpireAbandonedExpressOrders
{
    private const BATCH = 50;

    public function __construct(
        private ExpressOrderFactsBuilder $orderFacts,
        private MollieApi $mollie,
        private EffectInterpreter $effects,
        private Clock $clock,
        private EventLog $log,
        private int $graceSeconds
    ) {
    }

    public function run(): void
    {
        foreach ($this->orderFacts->abandonCandidates($this->clock->now() - $this->graceSeconds, self::BATCH) as $order) {
            $this->expire($order);
        }
    }

    private function expire(WC_Order $order): void
    {
        $sessionId = (string) $order->get_meta('_mollie_express_session_id');
        $paymentId = (string) $order->get_meta('_mollie_payment_id');
        $fields = ['order' => $order->get_id(), 'session' => $sessionId];

        try {
            [$session, $payment] = $this->askMollie($sessionId, $paymentId);
        } catch (Throwable $unreachable) {
            $this->log->info('express.abandoned.kept', $fields + ['reason' => 'mollie_unreachable']);

            return;
        }

        $effects = AbandonDecision::decide($session, $payment);
        $status = $payment !== null ? $payment->status() : ($session !== null ? $session->status() : 'unknown');
        if ($effects === []) {
            $this->log->info('express.abandoned.kept', $fields + ['reason' => $status]);

            return;
        }

        // The webhook may have paid the order while Mollie was being asked.
        $current = wc_get_order($order->get_id());
        if (!$current instanceof WC_Order || !$current->has_status('pending')) {
            $this->log->info('express.abandoned.kept', $fields + ['reason' => 'no_longer_pending']);

            return;
        }

        $this->effects->apply($current, $effects);
        $this->log->info('express.abandoned.cancelled', $fields + ['reason' => $status]);
    }

    /**
     * @return array{0: ?ExpressSession, 1: ?PaymentSnapshot}
     */
    private function askMollie(string $sessionId, string $paymentId): array
    {
        if ($paymentId !== '') {
            return [null, $this->mollie->payment($paymentId)];
        }

        return [$this->mollie->session($sessionId), null];
    }
}
