<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Flow;

use Mollie\WooCommerce\ExpressComponent\Rules\ExpressOrderMatch;
use Mollie\WooCommerce\ExpressComponent\Rules\FirstSight;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderWriter;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\OrphanedExpressPayments;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\Payment\OrderLockTimeout;
use Mollie\WooCommerce\SDK\MollieApi;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use Mollie\WooCommerce\Shared\Values\Refuse;
use Throwable;
use WC_Order;

/**
 * Matches a webhook payment the plugin did not create to its express order by express_ref.
 */
final class ResolveExpressPayment
{
    private const FLOW = 'express.payment.resolve';

    /**
     * @param array<string, array{gatewayId: string, paidAs: string}> $wallets
     * @param callable(): array<int, string> $registeredGatewayIds
     */
    public function __construct(
        private MollieApi $mollie,
        private ExpressOrderFactsBuilder $orderFacts,
        private OrderLock $lock,
        private ExpressOrderWriter $writer,
        private EventLog $log,
        private OrphanedExpressPayments $orphaned,
        private array $wallets,
        private $registeredGatewayIds
    ) {
    }

    /**
     * @throws OrderLockTimeout Retryable; nothing was written.
     */
    public function resolve(string $paymentId): ?WC_Order
    {
        // Orders API ids and malformed ids are never express payments.
        if (preg_match('/^tr_[A-Za-z0-9]+$/', $paymentId) !== 1) {
            return null;
        }

        $started = microtime(true);
        $this->log->step(self::FLOW . '.started', ['mollie_id' => $paymentId, 'entry' => 'webhook']);
        $order = null;
        $result = 'failed';
        try {
            $order = $this->matchToOrder($paymentId, $result);

            return $order;
        } finally {
            $this->log->step(self::FLOW . '.finished', [
                'order' => $order instanceof WC_Order ? $order->get_id() : 0,
                'result' => $result,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            $this->log->flush($result === 'failed');
        }
    }

    /**
     * @param-out string $result
     * @throws OrderLockTimeout
     */
    private function matchToOrder(string $paymentId, string &$result): ?WC_Order
    {
        try {
            $payment = $this->mollie->payment($paymentId);
        } catch (Throwable $unavailable) {
            // The exception text holds Mollie's response body, so it is not logged.
            $this->log->warning('express.webhook.unmatched', ['mollie_id' => $paymentId, 'reason' => 'payment_unavailable']);
            $result = 'unmatched';

            return null;
        }

        $order = $this->orderFacts->orderByRef((string) $payment->expressRef());
        $facts = $order instanceof WC_Order ? $this->orderFacts->fromOrder($order) : null;

        $decision = ExpressOrderMatch::admit($payment, $facts);
        $this->log->info('rule.decided', [
            'order' => $order instanceof WC_Order ? $order->get_id() : 0,
            'rule' => 'ExpressOrderMatch',
            'verdict' => $decision instanceof Refuse ? $decision->code() : 'admit',
            'inputs' => $this->matchInputs($payment, $facts),
        ]);
        if ($decision instanceof Refuse || $order === null || $facts === null) {
            $reason = $decision instanceof Refuse ? $decision->code() : 'unknown_ref';
            $this->log->warning('express.webhook.unmatched', [
                'mollie_id' => $paymentId,
                'reason' => $reason,
            ]);
            $this->traceRefusedPayment($payment, $order, $reason);
            $result = 'unmatched';

            return null;
        }

        $firstSight = FirstSight::decide($payment, $facts, $this->wallets, ($this->registeredGatewayIds)());
        $this->log->info('rule.decided', [
            'order' => $facts->orderId(),
            'rule' => 'FirstSight',
            'verdict' => $firstSight->gatewayId() !== null ? 'gateway' : 'unmatched',
            'inputs' => sprintf('method=%s gateway=%s', (string) $payment->method(), (string) $firstSight->gatewayId()),
        ]);
        $order = $this->lock->withFreshOrder($order->get_id(), function (WC_Order $fresh) use ($firstSight): WC_Order {
            $this->writer->recordFirstSight($fresh, $firstSight);

            return $fresh;
        });

        $this->log->info('express.payment.matched', [
            'order' => $order->get_id(),
            'mollie_id' => $paymentId,
            'wallet' => (string) $payment->method(),
            'status' => $payment->status(),
        ]);
        $result = 'matched';

        return $order;
    }

    private function matchInputs(PaymentSnapshot $payment, ?ExpressOrderFacts $facts): string
    {
        return sprintf(
            'status=%s ref=%d order=%d needs_payment=%d tracked=%d webhook_needs_payment=%d cancelled=%d cancelled_by=%s',
            $payment->status(),
            $payment->expressRef() !== null ? 1 : 0,
            $facts !== null ? 1 : 0,
            $facts !== null && $facts->needsPayment() ? 1 : 0,
            $facts !== null && $facts->trackedPaymentId() !== null ? 1 : 0,
            $facts !== null && $facts->webhookNeedsPayment() ? 1 : 0,
            $facts !== null && $facts->cancelled() ? 1 : 0,
            $facts !== null ? (string) $facts->cancelledBy() : ''
        );
    }

    /**
     * @throws OrderLockTimeout Retryable; nothing was written.
     */
    private function traceRefusedPayment(PaymentSnapshot $payment, ?WC_Order $order, string $reason): void
    {
        $ref = (string) $payment->expressRef();
        if ($ref === '' || !ExpressOrderMatch::tookMoney($payment)) {
            return;
        }

        $amount = $payment->amount();
        if ($order instanceof WC_Order) {
            $this->lock->withFreshOrder($order->get_id(), function (WC_Order $fresh) use ($payment, $amount, $reason): void {
                $this->writer->recordRefusedPayment($fresh, $payment->id(), $amount->toDecimal(), $amount->currency(), $reason);
            });
        }

        $this->log->error('express.payment.orphaned', [
            'mollie_id' => $payment->id(),
            'reason' => $reason,
            'status' => $payment->status(),
            'amount' => $amount->toDecimal(),
            'currency' => $amount->currency(),
        ]);
        $this->orphaned->remember($payment->id(), $reason, $amount->toDecimal(), $amount->currency());
    }
}
