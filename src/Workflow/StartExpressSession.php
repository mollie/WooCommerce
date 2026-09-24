<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Workflow;

use Mollie\WooCommerce\Adapter\Mollie\MollieApi;
use Mollie\WooCommerce\Adapter\Mollie\MollieCallFailed;
use Mollie\WooCommerce\Adapter\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressSessionBudget;
use Mollie\WooCommerce\Adapter\WooCommerce\ExpressSessionStore;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Adapter\WordPress\ExpressFactsBuilder;
use Mollie\WooCommerce\Adapter\WordPress\ExpressUrls;
use Mollie\WooCommerce\Core\Clock;
use Mollie\WooCommerce\Core\Express\ExpressAvailability;
use Mollie\WooCommerce\Core\Express\PricingFingerprint;
use Mollie\WooCommerce\Core\Express\SessionLines;
use Mollie\WooCommerce\Core\Express\SessionPayload;
use Mollie\WooCommerce\Core\Security\IdempotencyKey;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\ExpressAvailabilityResult;

/**
 * Hands the shopper a Checkout Session token for the express area, creating a session at Mollie
 * only when it must (ADR-012, ADR-013, S-04).
 *
 * An anonymous visitor triggers this by viewing the checkout, so the work is budgeted: no Mollie
 * call while the cart cannot be paid or its shipping is incomplete; the open session is handed out
 * again while the checkout is priced the same and it has time left; a new one is taken from the
 * caller's budget and created under a deterministic idempotency key. Nothing is remembered on
 * failure, so the next request starts clean.
 */
final class StartExpressSession
{
    private const INTENT = 'express.session.v1';

    public function __construct(
        private CartFactsBuilder $cartFacts,
        private ExpressFactsBuilder $expressFacts,
        private ExpressSessionStore $store,
        private ExpressSessionBudget $budget,
        private ExpressUrls $urls,
        private MollieApi $mollie,
        private Clock $clock,
        private EventLog $log,
        private int $reuseMarginSeconds
    ) {
    }

    public function start(string $surface): ExpressSessionResult
    {
        $cart = $this->cartFacts->fromCart() ?? new CartFacts([], false, false, false);
        $shop = $this->expressFacts->shopFacts();

        $availability = ExpressAvailability::resolve($this->expressFacts->settings(), $shop, $cart, $surface);
        if ($availability->status() !== ExpressAvailabilityResult::AVAILABLE) {
            return $this->refuse($surface, (string) $availability->reason(), 409);
        }

        $fingerprint = PricingFingerprint::of($cart);
        $remembered = $this->store->remembered();
        if ($remembered !== null) {
            if (
                $remembered['fingerprint'] === $fingerprint
                && $remembered['expiresAt'] - $this->clock->now() > $this->reuseMarginSeconds
            ) {
                $this->log->info('express.session.reused', ['session' => $remembered['id'], 'surface' => $surface]);

                return ExpressSessionResult::started($remembered['token'], gmdate('c', $remembered['expiresAt']));
            }
            // A new price, or too little time left: this token must never be handed out again.
            $this->store->forget();
        }

        if (!$this->budget->take()) {
            return $this->refuse($surface, 'budget_exhausted', 429);
        }

        return $this->create($cart, $fingerprint, $shop->mode(), $surface);
    }

    private function create(CartFacts $cart, string $fingerprint, string $mode, string $surface): ExpressSessionResult
    {
        $attempt = $this->store->nextAttempt();
        $customer = $this->store->customerKey();
        $ref = $this->store->expressRef($fingerprint, $attempt);

        $payload = SessionPayload::build(
            $cart,
            SessionLines::fromCart($cart),
            $this->urls->returnUrl($ref),
            $this->urls->webhookUrl(),
            ['express_ref' => $ref],
            SessionPayload::requiredCustomerDetails()
        );
        $key = IdempotencyKey::for(self::INTENT, [
            'customer' => $customer,
            'fingerprint' => $fingerprint,
            'attempt' => $attempt,
        ]);

        $started = microtime(true);
        try {
            $session = $this->mollie->createSession($payload, $key);
            $expiresAt = strtotime($session->expiresAt());
            if ($session->clientAccessToken() === '' || $expiresAt === false) {
                throw MollieCallFailed::fromThrowable(new \UnexpectedValueException('', 0));
            }
        } catch (MollieCallFailed $failure) {
            $this->log->error('express.session.failed', [
                'surface' => $surface,
                'kind' => $failure->kind(),
                'ms' => $this->msSince($started),
            ]);

            return $failure->kind() === MollieCallFailed::VALIDATION
                ? ExpressSessionResult::refused('session_refused', 502)
                : ExpressSessionResult::refused('mollie_unavailable', 503);
        }

        $this->store->remember($session->id(), $session->clientAccessToken(), $expiresAt, $fingerprint, $ref, $attempt);
        $total = $cart->total();
        $this->log->info('express.session.created', [
            'session' => $session->id(),
            'surface' => $surface,
            'mode' => $mode,
            'amount' => $total === null ? '' : $total->toDecimal(),
            'currency' => $total === null ? '' : $total->currency(),
            'ms' => $this->msSince($started),
        ]);

        return ExpressSessionResult::started($session->clientAccessToken(), $session->expiresAt());
    }

    private function refuse(string $surface, string $reason, int $httpStatus): ExpressSessionResult
    {
        $this->log->warning('express.session.refused', ['surface' => $surface, 'reason' => $reason]);

        return ExpressSessionResult::refused($reason, $httpStatus);
    }

    private function msSince(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
