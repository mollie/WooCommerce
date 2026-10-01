<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Flow;

use Mollie\WooCommerce\ExpressComponent\Rules\ExpressOrderMatch;
use Mollie\WooCommerce\ExpressComponent\Rules\FirstSight;
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

        try {
            $payment = $this->mollie->payment($paymentId);
        } catch (Throwable $unavailable) {
            // The exception text holds Mollie's response body, so it is not logged.
            $this->log->warning('express.webhook.unmatched', ['mollie_id' => $paymentId, 'reason' => 'payment_unavailable']);

            return null;
        }

        $order = $this->orderFacts->orderByRef((string) $payment->expressRef());
        $facts = $order instanceof WC_Order ? $this->orderFacts->fromOrder($order) : null;

        $decision = ExpressOrderMatch::admit($payment, $facts);
        if ($decision instanceof Refuse || $order === null || $facts === null) {
            $reason = $decision instanceof Refuse ? $decision->code() : 'unknown_ref';
            $this->log->warning('express.webhook.unmatched', [
                'mollie_id' => $paymentId,
                'reason' => $reason,
            ]);
            $this->reportOrphan($payment, $order, $reason);

            return null;
        }

        $firstSight = FirstSight::decide($payment, $facts, $this->wallets, ($this->registeredGatewayIds)());
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

        return $order;
    }

    private function reportOrphan(PaymentSnapshot $payment, ?WC_Order $order, string $reason): void
    {
        $ref = (string) $payment->expressRef();
        if ($order instanceof WC_Order || $ref === '' || !in_array($payment->status(), ['paid', 'authorized'], true)) {
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
