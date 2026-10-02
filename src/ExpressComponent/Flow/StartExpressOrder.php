<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Flow;

use Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactory;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderWriter;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressSessionStore;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\Payment\OrderLockTimeout;
use Mollie\WooCommerce\Shared\Clock;
use Mollie\WooCommerce\Shared\Values\Refuse;
use Throwable;
use WC_Order;

/**
 * The lock on express_ref makes retries and double taps reuse the order.
 */
final class StartExpressOrder
{
    private const FLOW = 'express.order.start';

    private const HTTP_CONFLICT = 409;

    private const REFUSALS_THAT_SPEND_SESSION = ['cart_changed', 'order_not_payable'];

    public function __construct(
        private CartFactsBuilder $cartFacts,
        private ExpressFactsBuilder $expressFacts,
        private ExpressOrderFactsBuilder $orderFacts,
        private ExpressSessionStore $store,
        private ExpressOrderFactory $factory,
        private ExpressOrderWriter $writer,
        private OrderLock $lock,
        private Clock $clock,
        private EventLog $log
    ) {
    }

    public function start(): ExpressOrderResult
    {
        $started = microtime(true);
        $this->log->step(self::FLOW . '.started', ['entry' => 'submit']);
        $result = null;
        $endedBadly = true;
        try {
            $result = $this->startOrRefuse($endedBadly);

            return $result;
        } finally {
            $this->log->step(self::FLOW . '.finished', [
                'result' => $result === null ? 'failed' : ($result->isOk() ? 'ok' : (string) $result->code()),
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            $this->log->flush($endedBadly);
        }
    }

    /**
     * @param-out bool $endedBadly
     */
    private function startOrRefuse(bool &$endedBadly): ExpressOrderResult
    {
        $session = $this->orderFacts->rememberedSession();
        if ($session === null) {
            $endedBadly = false;

            return $this->refuse(null, 'session_missing', self::HTTP_CONFLICT);
        }

        try {
            $result = $this->lock->withLock($session->expressRef(), function () use ($session): ExpressOrderResult {
                return $this->startLocked($session);
            });
            $endedBadly = false;

            return $result;
        } catch (OrderLockTimeout $timeout) {
            return $this->refuse($session, 'try_again', 503);
        }
    }

    private function startLocked(RememberedSession $session): ExpressOrderResult
    {
        $order = $this->orderFacts->orderByRef($session->expressRef());
        $existing = $order instanceof WC_Order ? $this->orderFacts->fromOrder($order) : null;
        $cart = $this->cartFacts->fromCart() ?? new CartFacts([], false, false, false);
        $decision = StartOrderDecision::admit($session, $existing, $cart, $this->clock->now());
        $this->log->info('rule.decided', [
            'order' => $existing !== null ? $existing->orderId() : 0,
            'session' => $session->sessionId(),
            'rule' => 'StartOrderDecision',
            'verdict' => $decision instanceof Refuse ? $decision->code() : 'admit',
            'inputs' => sprintf(
                'order=%d needs_payment=%d needs_shipping=%d shipping_complete=%d rate_chosen=%d',
                $existing !== null ? 1 : 0,
                $existing !== null && $existing->needsPayment() ? 1 : 0,
                $cart->needsShipping() ? 1 : 0,
                $cart->shippingDestinationComplete() ? 1 : 0,
                $cart->shippingRateChosen() ? 1 : 0
            ),
        ]);

        if ($decision instanceof Refuse) {
            if (in_array($decision->code(), self::REFUSALS_THAT_SPEND_SESSION, true)) {
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
            return $this->refuse($session, 'cart_invalid', self::HTTP_CONFLICT, $reason);
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
            $priced = StartOrderDecision::admitCreatedOrder($this->orderFacts->total($order), $total);
            if ($priced instanceof Refuse) {
                $this->factory->delete($order);

                return $this->refuse($session, $priced->code(), $priced->httpStatus());
            }
            $order = $this->lock->withFreshOrder(
                $order->get_id(),
                function (WC_Order $fresh) use ($session, $mode, $gatewayId, $wallet): WC_Order {
                    $this->writer->stampNewOrder($fresh, $session, $mode, $gatewayId, $wallet);

                    return $fresh;
                }
            );
            // WooCommerce's save logs failures instead of throwing; an unfindable order would be duplicated.
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
     * The first webhook corrects it to the wallet that paid.
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
        // Anyone can cause a refusal: only a failed creation is a warning.
        if ($code === 'creation_failed') {
            $this->log->warning('express.order.refused', $fields);
        } else {
            $this->log->info('express.order.refused', $fields);
        }

        return ExpressOrderResult::refused($code, $httpStatus, $reason);
    }
}
