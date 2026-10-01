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
use Mollie\WooCommerce\Core\Express\AddressMapping;
use Mollie\WooCommerce\Core\Express\StartOrderDecision;
use Mollie\WooCommerce\Core\Express\WalletVisibility;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\Money;
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
        $facts = $this->orderFacts->fromStore();
        $ref = $facts->expressRef();
        if (!$facts->hasSession() || $ref === null) {
            return $this->refuse($facts, 'session_missing', self::REFUSED);
        }

        try {
            return $this->lock->withLock($ref, function () use ($facts): ExpressOrderResult {
                return $this->startLocked($this->orderFacts->withExistingOrder($facts));
            });
        } catch (OrderLockTimeout $timeout) {
            return $this->refuse($facts, 'try_again', 503);
        }
    }

    private function startLocked(ExpressOrderFacts $facts): ExpressOrderResult
    {
        $cart = $this->cartFacts->fromCart() ?? new CartFacts([], false, false, false);
        $decision = StartOrderDecision::decide($facts, $cart, $this->clock->now());

        if ($decision instanceof Refuse) {
            if ($decision->code() === 'cart_changed') {
                // Priced for another checkout: this session must never be used again.
                $this->store->forget();
            }

            return $this->refuse($facts, $decision->code(), $decision->httpStatus());
        }
        if ($decision === StartOrderDecision::REUSE) {
            $this->log->info('express.order.reused', ['order' => $facts->existingOrderId(), 'session' => $facts->sessionId()]);

            return $this->answer();
        }

        return $this->create($facts, $cart);
    }

    private function create(ExpressOrderFacts $facts, CartFacts $cart): ExpressOrderResult
    {
        $reason = $this->factory->invalidCartReason();
        if ($reason !== null) {
            return $this->refuse($facts, 'cart_invalid', self::REFUSED, $reason);
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
            if (!$this->sameAmount($order, $total)) {
                $this->factory->delete($order);

                return $this->refuse($facts, 'amount_mismatch', self::REFUSED);
            }
            $order = $this->effects->apply($order, StartOrderDecision::stamps($facts, $mode, $gatewayId, $wallet));
            // WooCommerce's order save logs a failure instead of throwing. An order a repeat submit
            // cannot find by its ref would be orphaned and followed by a second one.
            if ($this->orderFacts->orderByRef((string) $facts->expressRef())?->get_id() !== $order->get_id()) {
                throw new \RuntimeException('The express order was not stamped.');
            }
        } catch (Throwable $error) {
            if ($order instanceof WC_Order && $order->get_id() > 0) {
                $this->factory->delete($order);
            }

            return $this->refuse($facts, 'creation_failed', 500);
        }

        $this->log->info('express.order.created', [
            'order' => $order->get_id(),
            'session' => $facts->sessionId(),
            'wallet' => $wallet,
            'amount' => $total->toDecimal(),
            'currency' => $total->currency(),
        ]);

        return $this->answer();
    }

    /**
     * The details the store holds for this shopper, in Mollie's shape: they win over what the wallet
     * collected, and the shipping address at Mollie is the one the cost was calculated from.
     */
    private function answer(): ExpressOrderResult
    {
        $details = $this->orderFacts->shopperDetails();

        return ExpressOrderResult::ok(
            AddressMapping::toMollie($details['billing']),
            AddressMapping::toMollie($details['shipping'])
        );
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

    /**
     * The order total, written with the precision of the cart total, is that amount to the cent.
     */
    private function sameAmount(WC_Order $order, Money $total): bool
    {
        $expected = $total->toDecimal();
        $decimals = str_contains($expected, '.') ? strlen(substr($expected, strpos($expected, '.') + 1)) : 0;

        return number_format((float) $order->get_total('edit'), $decimals, '.', '') === $expected
            && $order->get_currency() === $total->currency();
    }

    private function refuse(ExpressOrderFacts $facts, string $code, int $httpStatus, ?string $reason = null): ExpressOrderResult
    {
        $this->log->warning('express.order.refused', ['session' => (string) $facts->sessionId(), 'reason' => $code]);

        return ExpressOrderResult::refused($code, $httpStatus, $reason);
    }
}
