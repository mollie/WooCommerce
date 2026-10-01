<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Flow;

use InvalidArgumentException;
use Mollie\WooCommerce\ExpressComponent\Rules\AbandonDecision;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderWriter;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\SDK\MollieApi;
use Mollie\WooCommerce\Shared\Clock;
use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use Throwable;
use WC_Order;

/**
 * Cancels abandoned express orders only when Mollie confirms they can no longer be paid.
 */
final class ExpireAbandonedExpressOrders
{
    private const BATCH = 50;

    public function __construct(
        private ExpressOrderFactsBuilder $orderFacts,
        private MollieApi $mollie,
        private OrderLock $lock,
        private ExpressOrderWriter $writer,
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

        $canNoLongerBePaid = AbandonDecision::decide($session, $payment);
        $status = $payment !== null ? $payment->status() : ($session !== null ? $session->status() : 'unknown');
        if (!$canNoLongerBePaid) {
            $this->log->info('express.abandoned.kept', $fields + ['reason' => $status]);

            return;
        }

        $handledEvent = ($payment !== null ? $payment->id() : $sessionId) . ':' . $status;
        try {
            $cancelled = $this->lock->withFreshOrder($order->get_id(), function (WC_Order $fresh) use ($handledEvent): bool {
                // The webhook may have paid the order while Mollie was being asked.
                if (!$fresh->has_status('pending')) {
                    return false;
                }
                $this->writer->cancelAbandoned($fresh, $handledEvent);

                return true;
            });
        } catch (InvalidArgumentException $deleted) {
            $cancelled = false;
        }
        if (!$cancelled) {
            $this->log->info('express.abandoned.kept', $fields + ['reason' => 'no_longer_pending']);

            return;
        }

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
