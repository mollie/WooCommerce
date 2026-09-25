<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Workflow;

use Mollie\WooCommerce\Adapter\Mollie\MollieApi;
use Mollie\WooCommerce\Adapter\WooCommerce\EffectInterpreter;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Adapter\WordPress\OrderLockTimeout;
use Mollie\WooCommerce\Core\Express\ExpressOrderMatch;
use Mollie\WooCommerce\Core\Express\FirstSightEffects;
use Mollie\WooCommerce\Core\Types\Refuse;
use Throwable;
use WC_Order;
/**
 * Turns a payment the plugin never created into a known one, for both webhook paths.
 *
 * Runs only when neither indexed lookup found an order. The payment is fetched from Mollie — the
 * request gave nothing but its id — and the order is the one carrying the ref in its metadata. On a
 * match the first-sight effects are applied under the per-order lock, so a webhook and a second
 * resolution racing on the same first sight converge on one write. What happens to the order's
 * status is then decided by the existing doPaymentForOrder(), exactly as today.
 */
final class ResolveExpressPayment
{
    /**
     * @param array<string, array{gatewayId: string, mollieMethod: string}> $wallets The wallets table of config/express.php.
     * @param callable(): array<int, string> $registeredGatewayIds
     */
    public function __construct(private MollieApi $mollie, private ExpressOrderFactsBuilder $orderFacts, private EffectInterpreter $effects, private EventLog $log, private array $wallets, private $registeredGatewayIds)
    {
    }
    /**
     * @return WC_Order|null The matched order, its first-sight effects applied; null when no order matches.
     *
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
            // The adapter's text carries Mollie's response body; it is never logged.
            $this->log->warning('express.webhook.unmatched', ['mollie_id' => $paymentId, 'reason' => 'payment_unavailable']);
            return null;
        }
        $order = $this->orderFacts->orderByRef((string) $payment->expressRef());
        $facts = $order instanceof WC_Order ? $this->orderFacts->forResolution($order) : null;
        $decision = ExpressOrderMatch::decide($payment, $facts);
        if ($decision instanceof Refuse || $order === null || $facts === null) {
            $this->log->warning('express.webhook.unmatched', ['mollie_id' => $paymentId, 'reason' => $decision instanceof Refuse ? $decision->code() : 'unknown_ref']);
            return null;
        }
        $effects = FirstSightEffects::for($payment, $facts, $this->wallets, ($this->registeredGatewayIds)());
        $order = $this->effects->apply($order, $effects);
        $this->log->info('express.payment.matched', ['order' => $order->get_id(), 'mollie_id' => $paymentId, 'wallet' => (string) $payment->method(), 'status' => $payment->status()]);
        return $order;
    }
}
