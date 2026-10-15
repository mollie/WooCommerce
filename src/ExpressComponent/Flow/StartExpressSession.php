<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Flow;

use Mollie\WooCommerce\ExpressComponent\Entry\ExpressUrls;
use Mollie\WooCommerce\ExpressComponent\Rules\ExpressAvailability;
use Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint;
use Mollie\WooCommerce\ExpressComponent\Rules\SessionLines;
use Mollie\WooCommerce\ExpressComponent\Rules\SessionReuse;
use Mollie\WooCommerce\ExpressComponent\Rules\SessionPayload;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressAvailabilityResult;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressSessionBudget;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressSessionStore;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\SDK\IdempotencyKey;
use Mollie\WooCommerce\SDK\MollieApi;
use Mollie\WooCommerce\SDK\MollieCallFailed;
use Mollie\WooCommerce\Shared\Clock;
/**
 * Anonymous visitors trigger this, so an open session is reused and new ones are budgeted.
 */
final class StartExpressSession
{
    private const INTENT = 'express.session.v1';
    public function __construct(private CartFactsBuilder $cartFacts, private ExpressFactsBuilder $expressFacts, private ExpressSessionStore $store, private ExpressSessionBudget $budget, private ExpressUrls $urls, private MollieApi $mollie, private Clock $clock, private EventLog $log, private int $reuseMarginSeconds)
    {
    }
    public function start(string $surface, string $callerAddress): \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult
    {
        $cart = $this->cartFacts->fromCart() ?? new CartFacts([], \false, \false, \false);
        $shop = $this->expressFacts->shopFacts();
        $availability = ExpressAvailability::resolve($this->expressFacts->settings(), $shop, $cart, $surface);
        if ($availability->status() !== ExpressAvailabilityResult::AVAILABLE) {
            return $this->refuse($surface, (string) $availability->reason(), 409);
        }
        $fingerprint = PricingFingerprint::of($cart);
        $remembered = $this->store->remembered();
        if ($remembered !== null) {
            $fits = SessionReuse::fits($remembered['fingerprint'], $remembered['expiresAt'], $fingerprint, $this->clock->now(), $this->reuseMarginSeconds);
            if ($fits) {
                $this->log->info('express.session.reused', ['session' => $remembered['id'], 'surface' => $surface]);
                return \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult::started($remembered['token'], gmdate('c', $remembered['expiresAt']));
            }
            $this->store->forget();
        }
        $budget = $this->budget->take($callerAddress);
        if ($budget !== ExpressSessionBudget::TAKEN) {
            if ($this->budget->refusalIsNews()) {
                $this->log->warning('express.session.budget_spent', ['surface' => $surface, 'reason' => $budget]);
            }
            return $this->refuse($surface, 'budget_exhausted', 429);
        }
        return $this->create($cart, $fingerprint, $shop->mode(), $surface);
    }
    private function create(CartFacts $cart, string $fingerprint, string $mode, string $surface): \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult
    {
        $attempt = $this->store->nextAttempt();
        $customer = $this->store->customerKey();
        $ref = $this->store->expressRef($fingerprint, $attempt);
        $payload = SessionPayload::build($cart, SessionLines::fromCart($cart), $this->urls->returnUrl($ref), $this->urls->webhookUrl(), ['express_ref' => $ref], SessionPayload::requiredCustomerDetails());
        $key = IdempotencyKey::for(self::INTENT, ['customer' => $customer, 'fingerprint' => $fingerprint, 'attempt' => $attempt]);
        $started = microtime(\true);
        try {
            $session = $this->mollie->createSession($payload, $key);
            $expiresAt = strtotime($session->expiresAt());
            if ($session->clientAccessToken() === '' || $expiresAt === \false) {
                throw MollieCallFailed::fromThrowable(new \UnexpectedValueException('', 0));
            }
        } catch (MollieCallFailed $failure) {
            $this->log->error('express.session.failed', ['surface' => $surface, 'kind' => $failure->kind(), 'ms' => $this->msSince($started)]);
            return $failure->kind() === MollieCallFailed::VALIDATION ? \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult::refused('session_refused', 502) : \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult::refused('mollie_unavailable', 503);
        }
        $this->store->remember($session->id(), $session->clientAccessToken(), $expiresAt, $fingerprint, $ref, $attempt);
        $total = $cart->total();
        $this->log->info('express.session.created', ['session' => $session->id(), 'surface' => $surface, 'mode' => $mode, 'amount' => $total === null ? '' : $total->toDecimal(), 'currency' => $total === null ? '' : $total->currency(), 'ms' => $this->msSince($started)]);
        return \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult::started($session->clientAccessToken(), $session->expiresAt());
    }
    private function refuse(string $surface, string $reason, int $httpStatus): \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult
    {
        // Anyone can cause a refusal: info, not a warning.
        $this->log->info('express.session.refused', ['surface' => $surface, 'reason' => $reason]);
        return \Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult::refused($reason, $httpStatus);
    }
    private function msSince(float $started): int
    {
        return (int) round((microtime(\true) - $started) * 1000);
    }
}
