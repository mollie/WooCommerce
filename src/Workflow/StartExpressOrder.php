<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Workflow;

use Mollie\WooCommerce\Adapter\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\Adapter\WooCommerce\EffectInterpreter;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressOrderFactory;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressSessionStore;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerce\Adapter\WordPress\OrderLockTimeout;
use Mollie\WooCommerce\Core\Clock;
use Mollie\WooCommerce\Core\Express\StartOrderDecision;
use Mollie\WooCommerce\Core\Express\WalletVisibility;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\RememberedSession;
use Mollie\WooCommerce\Core\Types\Refuse;
use Throwable;
use WC_Order;

/**
 * Creates the pending order of an express checkout at Mollie's submit event, after the shopper
 * authorised in the wallet and before Mollie creates the payment.
 *
 * Nothing comes from the caller: the session is the one remembered for this shopper, the cart and
 * the details are what WooCommerce holds. One order per express_ref is enforced under the lock, so
 * a double tap, a retry or two tabs get the same order. A refusal leaves no order behind.
 */
final class StartExpressOrder
{
    private const REFUSED = 409;

    /**
     * Refusals after which the remembered session is dropped.
     */
    private const SESSION_SPENT = ['cart_changed', 'order_not_payable'];

    public function __construct(
        private CartFactsBuilder $cartFacts,
        private ExpressFactsBuilder $expressFacts,
        private ExpressOrderFactsBuilder $orderFacts,
        private ExpressSessionStore $store,
        private ExpressOrderFactory $factory,
        private EffectInterpreter $effects,
        private OrderLock $lock,
        private Clock $clock,
        private EventLog $log
    ) {
    }

    public function start(): ExpressOrderResult
    {
        $session = $this->orderFacts->rememberedSession();
        if ($session === null) {
            return $this->refuse(null, 'session_missing', self::REFUSED);
        }

        try {
            return $this->lock->withLock($session->expressRef(), function () use ($session): ExpressOrderResult {
                return $this->startLocked($session);
            });
        } catch (OrderLockTimeout $timeout) {
            return $this->refuse($session, 'try_again', 503);
        }
    }

    private function startLocked(RememberedSession $session): ExpressOrderResult
    {
        $order = $this->orderFacts->orderByRef($session->expressRef());
        $existing = $order instanceof WC_Order ? $this->orderFacts->fromOrder($order) : null;
        $cart = $this->cartFacts->fromCart() ?? new CartFacts([], false, false, false);
        $decision = StartOrderDecision::decide($session, $existing, $cart, $this->clock->now());

        if ($decision instanceof Refuse) {
            if (in_array($decision->code(), self::SESSION_SPENT, true)) {
                // Priced for another checkout, or already paid: this session must never be used again.
                $this->store->forget();
            }

            return $this->refuse($session, $decision->code(), $decision->httpStatus());
        }
        if ($existing !== null) {
            $this->log->info('express.order.reused', ['order' => $existing->orderId(), 'session' => $session->sessionId()]);

            return ExpressOrderResult::ok();
        }

        return $this->create($session, $cart);
    }

    private function create(RememberedSession $session, CartFacts $cart): ExpressOrderResult
    {
        $reason = $this->factory->invalidCartReason();
        if ($reason !== null) {
            return $this->refuse($session, 'cart_invalid', self::REFUSED, $reason);
        }

        $total = $cart->total();
        $order = null;
        try {
            if ($total === null) {
                throw new \UnexpectedValueException('The cart has no total.');
            }
            $mode = $this->expressFacts->shopFacts()->mode();
            [$wallet, $gatewayId] = $this->provisionalWallet();
            $order = $this->factory->create($this->orderFacts->shopperDetails());
            // The fingerprint matched, so the cart total is the amount the session was priced for.
            $orderTotal = $this->orderFacts->total($order);
            if ($orderTotal === null || !$orderTotal->isSameAs($total)) {
                $this->factory->delete($order);

                return $this->refuse($session, 'amount_mismatch', self::REFUSED);
            }
            $order = $this->effects->apply($order, StartOrderDecision::stamps($session, $mode, $gatewayId, $wallet));
            // WooCommerce's order save logs a failure instead of throwing. An order a repeat submit
            // cannot find by its ref would be orphaned and followed by a second one.
            if ($this->orderFacts->orderByRef($session->expressRef())?->get_id() !== $order->get_id()) {
                throw new \RuntimeException('The express order was not stamped.');
            }
        } catch (Throwable $error) {
            if ($order instanceof WC_Order && $order->get_id() > 0) {
                $this->factory->delete($order);
            }

            return $this->refuse($session, 'creation_failed', 500);
        }

        $this->log->info('express.order.created', [
            'order' => $order->get_id(),
            'session' => $session->sessionId(),
            'wallet' => $wallet,
            'amount' => $total->toDecimal(),
            'currency' => $total->currency(),
        ]);

        return ExpressOrderResult::ok();
    }

    /**
     * The first wallet the checkout shows. The first webhook corrects it to the wallet that paid.
     *
     * @return array{0: string, 1: string} Wallet key and gateway id.
     */
    private function provisionalWallet(): array
    {
        $settings = $this->expressFacts->settings();
        $wallets = $settings->wallets();
        foreach (WalletVisibility::buttons($settings, $this->expressFacts->shopFacts()) as $wallet => $visible) {
            if ($visible) {
                return [$wallet, $wallets[$wallet]['gatewayId']];
            }
        }
        throw new \RuntimeException('No express wallet is visible.');
    }

    private function refuse(?RememberedSession $session, string $code, int $httpStatus, ?string $reason = null): ExpressOrderResult
    {
        $fields = ['session' => $session === null ? '' : $session->sessionId(), 'reason' => $code];
        // A refused submit is the shopper's normal flow and anyone may cause one, so it is written
        // only with the debug log on; an order the store failed to create is a problem.
        if ($code === 'creation_failed') {
            $this->log->warning('express.order.refused', $fields);
        } else {
            $this->log->info('express.order.refused', $fields);
        }

        return ExpressOrderResult::refused($code, $httpStatus, $reason);
    }
}
