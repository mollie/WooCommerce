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
use Mollie\WooCommerce\SDK\MollieCallFailed;
use Mollie\WooCommerce\Shared\Values\Admit;
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
     * @throws MollieCallFailed Retryable; Mollie could not be asked, nothing was written.
     */
    public function resolve(string $paymentId): ?WC_Order
    {
        // Only a tr_ id can be an express payment.
        if (preg_match('/^tr_.+$/D', $paymentId) !== 1) {
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
     * @throws MollieCallFailed
     */
    private function matchToOrder(string $paymentId, string &$result): ?WC_Order
    {
        try {
            $payment = $this->mollie->payment($paymentId);
        } catch (MollieCallFailed $failed) {
            if ($failed->kind() !== MollieCallFailed::NOT_FOUND) {
                // A 200 would end Mollie's retries and leave a paid payment without its order.
                $this->log->warning('webhook.failed', ['mollie_id' => $paymentId, 'kind' => $failed->kind()]);

                throw $failed;
            }
            $this->log->warning('express.webhook.unmatched', ['mollie_id' => $paymentId, 'reason' => 'payment_unavailable']);
            $result = 'unmatched';

            return null;
        } catch (Throwable $unavailable) {
            // The exception text holds Mollie's response body, so it is not logged.
            $this->log->warning('express.webhook.unmatched', ['mollie_id' => $paymentId, 'reason' => 'payment_unavailable']);
            $result = 'unmatched';

            return null;
        }

        $found = $this->orderFacts->orderByRef((string) $payment->expressRef());
        if ($found === null) {
            $refusal = $this->decide($payment, null);
            $reason = $refusal instanceof Refuse ? $refusal->code() : 'unknown_ref';
            $this->reportUnmatched($payment, $reason);
            $result = 'unmatched';

            return null;
        }

        // Not an order fact, so it is read before the lock.
        $registeredGatewayIds = ($this->registeredGatewayIds)();
        $reason = null;
        $order = $this->lock->withFreshOrder(
            $found->get_id(),
            function (WC_Order $fresh) use ($payment, $registeredGatewayIds, &$reason): ?WC_Order {
                $facts = $this->orderFacts->fromOrder($fresh);
                $decision = $this->decide($payment, $facts);
                if ($decision instanceof Refuse) {
                    $reason = $decision->code();
                    $this->noteRefusedPayment($fresh, $payment, $reason);

                    return null;
                }

                $firstSight = FirstSight::decide($payment, $facts, $this->wallets, $registeredGatewayIds);
                $this->log->info('rule.decided', [
                    'order' => $facts->orderId(),
                    'rule' => 'FirstSight',
                    'verdict' => $firstSight->gatewayId() !== null ? 'gateway' : 'unmatched',
                    'inputs' => sprintf('method=%s gateway=%s', (string) $payment->method(), (string) $firstSight->gatewayId()),
                ]);
                $this->writer->recordFirstSight($fresh, $firstSight);

                return $fresh;
            }
        );
        if ($order === null) {
            $this->reportUnmatched($payment, (string) $reason);
            $result = 'unmatched';

            return null;
        }

        $this->log->info('express.payment.matched', [
            'order' => $order->get_id(),
            'mollie_id' => $paymentId,
            'wallet' => (string) $payment->method(),
            'status' => $payment->status(),
        ]);
        $result = 'matched';

        return $order;
    }

    private function decide(PaymentSnapshot $payment, ?ExpressOrderFacts $facts): Admit|Refuse
    {
        $decision = ExpressOrderMatch::admit($payment, $facts);
        $this->log->info('rule.decided', [
            'order' => $facts !== null ? $facts->orderId() : 0,
            'rule' => 'ExpressOrderMatch',
            'verdict' => $decision instanceof Refuse ? $decision->code() : 'admit',
            'inputs' => $this->matchInputs($payment, $facts),
        ]);

        return $decision;
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

    private function noteRefusedPayment(WC_Order $fresh, PaymentSnapshot $payment, string $reason): void
    {
        if (!ExpressOrderMatch::tookMoney($payment)) {
            return;
        }
        $amount = $payment->amount();
        $this->writer->recordRefusedPayment($fresh, $payment->id(), $amount->toDecimal(), $amount->currency(), $reason);
    }

    private function reportUnmatched(PaymentSnapshot $payment, string $reason): void
    {
        $fields = ['mollie_id' => $payment->id(), 'reason' => $reason];
        if ($reason === ExpressOrderMatch::MISSING_REF) {
            // Not an express payment: nothing went wrong.
            $this->log->info('express.webhook.unmatched', $fields);

            return;
        }
        $this->log->warning('express.webhook.unmatched', $fields);
        if (!ExpressOrderMatch::tookMoney($payment)) {
            return;
        }

        $amount = $payment->amount();
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
