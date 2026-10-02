<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Flow;

use InvalidArgumentException;
use Mollie\WooCommerce\ExpressComponent\Rules\AbandonDecision;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\AbandonVerdict;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderWriter;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\PendingExpressOrders;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\Payment\OrderLockTimeout;
use Mollie\WooCommerce\SDK\MollieApi;
use Mollie\WooCommerce\SDK\MollieCallFailed;
use Mollie\WooCommerce\Shared\Clock;
use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use Throwable;
use WC_Order;

/**
 * Mollie is asked outside the lock; the order is decided on under it.
 */
final class ExpireAbandonedExpressOrders
{
    private const FLOW = 'express.abandoned.expire';

    private const BATCH = 50;

    public function __construct(
        private ExpressOrderFactsBuilder $orderFacts,
        private MollieApi $mollie,
        private OrderLock $lock,
        private ExpressOrderWriter $writer,
        private Clock $clock,
        private EventLog $log,
        private PendingExpressOrders $pending,
        private int $graceSeconds,
        private int $giveUpSeconds
    ) {
    }

    public function run(): void
    {
        foreach ($this->orderFacts->abandonCandidates($this->clock->now() - $this->graceSeconds, self::BATCH) as $order) {
            $this->expire($order);
        }
        $this->pending->recount();
    }

    private function expire(WC_Order $order): void
    {
        $started = microtime(true);
        $this->log->step(self::FLOW . '.started', [
            'order' => $order->get_id(),
            'mollie_id' => (string) $order->get_meta('_mollie_payment_id'),
            'entry' => 'cleanup',
        ]);
        $result = 'failed';
        try {
            $result = $this->cancelIfUnpayable($order);
        } finally {
            $this->log->step(self::FLOW . '.finished', [
                'order' => $order->get_id(),
                'result' => $result,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            $this->log->flush($result === 'failed');
        }
    }

    /**
     * @return 'cancelled'|'kept'
     */
    private function cancelIfUnpayable(WC_Order $order): string
    {
        $sessionId = (string) $order->get_meta('_mollie_express_session_id');
        $paymentId = (string) $order->get_meta('_mollie_payment_id');
        $sinceExpiry = $this->clock->now() - (int) $order->get_meta('_mollie_express_expires_at');
        [$session, $payment, $unknownAtMollie] = $this->askMollie($sessionId, $paymentId);

        try {
            $verdict = $this->lock->withFreshOrder(
                $order->get_id(),
                function (WC_Order $fresh) use ($session, $payment, $unknownAtMollie, $sinceExpiry): AbandonVerdict {
                    // The webhook may have paid the order while Mollie was being asked.
                    $verdict = AbandonDecision::decide(
                        $fresh->has_status('pending'),
                        $session,
                        $payment,
                        $unknownAtMollie,
                        $sinceExpiry,
                        $this->giveUpSeconds
                    );
                    $this->log->info('rule.decided', [
                        'order' => $fresh->get_id(),
                        'rule' => 'AbandonDecision',
                        'verdict' => $verdict->cancels() ? 'cancel' : 'keep',
                        'inputs' => sprintf(
                            'pending=%d payment_status=%s session_status=%s unknown=%d reason=%s',
                            $fresh->has_status('pending') ? 1 : 0,
                            $payment !== null ? $payment->status() : '',
                            $session !== null ? $session->status() : '',
                            $unknownAtMollie ? 1 : 0,
                            $verdict->reason()
                        ),
                    ]);
                    if ($verdict->cancels()) {
                        $this->writer->cancelAbandoned($fresh, $this->handledEvent($session, $payment));
                    }

                    return $verdict;
                }
            );
        } catch (InvalidArgumentException $deleted) {
            $verdict = AbandonVerdict::keep(AbandonDecision::NO_LONGER_PENDING);
        } catch (OrderLockTimeout $busy) {
            $verdict = AbandonVerdict::keep('order_busy');
        }

        $fields = ['order' => $order->get_id(), 'session' => $sessionId, 'reason' => $verdict->reason()];
        if (!$verdict->cancels()) {
            $this->log->info('express.abandoned.kept', $fields);

            return 'kept';
        }
        $this->log->info('express.abandoned.cancelled', $fields);

        return 'cancelled';
    }

    /**
     * Never throws.
     *
     * @return array{0: ?ExpressSession, 1: ?PaymentSnapshot, 2: bool} Last: Mollie knows neither.
     */
    private function askMollie(string $sessionId, string $paymentId): array
    {
        try {
            return $paymentId !== ''
                ? [null, $this->mollie->payment($paymentId), false]
                : [$this->mollie->session($sessionId), null, false];
        } catch (MollieCallFailed $failed) {
            return [null, null, $failed->kind() === MollieCallFailed::NOT_FOUND];
        } catch (Throwable $unreachable) {
            return [null, null, false];
        }
    }

    /**
     * "<id>:<status>" as Mollie reported it, or empty.
     */
    private function handledEvent(?ExpressSession $session, ?PaymentSnapshot $payment): string
    {
        if ($payment !== null) {
            return $payment->id() . ':' . $payment->status();
        }

        return $session !== null ? $session->id() . ':' . $session->status() : '';
    }
}
