<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

/**
 * The token is a shopper credential: it lives only in the WC session.
 * The seed is lost with the attempt counter, so a counter that restarts never repeats a key.
 */
class ExpressSessionStore
{
    private const SESSION_KEY = 'mollie_express_session';

    private const ATTEMPTS_KEY = 'mollie_express_session_attempts';

    private const SEED_KEY = 'mollie_express_session_seed';

    /**
     * @return array{id: string, token: string, expiresAt: int, fingerprint: string, ref: string, attempt: int}|null
     */
    public function remembered(): ?array
    {
        $session = $this->session();
        $stored = $session ? $session->get(self::SESSION_KEY) : null;
        if (!is_array($stored) || !isset($stored['id'], $stored['token'], $stored['expiresAt'], $stored['fingerprint'], $stored['ref'], $stored['attempt'])) {
            return null;
        }

        return [
            'id' => (string) $stored['id'],
            'token' => (string) $stored['token'],
            'expiresAt' => (int) $stored['expiresAt'],
            'fingerprint' => (string) $stored['fingerprint'],
            'ref' => (string) $stored['ref'],
            'attempt' => (int) $stored['attempt'],
        ];
    }

    public function remember(string $id, string $token, int $expiresAt, string $fingerprint, string $ref, int $attempt): void
    {
        $session = $this->session();
        if (!$session) {
            return;
        }
        $session->set(self::SESSION_KEY, [
            'id' => $id,
            'token' => $token,
            'expiresAt' => $expiresAt,
            'fingerprint' => $fingerprint,
            'ref' => $ref,
            'attempt' => $attempt,
        ]);
        $session->set(self::ATTEMPTS_KEY, max($attempt, $this->attemptsSoFar()));
    }

    public function forget(): void
    {
        $session = $this->session();
        if ($session) {
            $session->set(self::SESSION_KEY, null);
        }
    }

    public function nextAttempt(): int
    {
        return $this->attemptsSoFar() + 1;
    }

    /** Hashed, never the raw customer id; one per WooCommerce session, not per customer. */
    public function customerKey(): string
    {
        $session = $this->session();
        $customer = $session ? (string) $session->get_customer_id() : '';

        return hash_hmac('sha256', $customer . '|' . $this->seed(), wp_salt('nonce'));
    }

    /** Derived, not random, so a retry sends the same body under the same idempotency key. */
    public function expressRef(string $fingerprint, int $attempt): string
    {
        return 'exr_' . substr(
            hash_hmac('sha256', $this->customerKey() . '|' . $fingerprint . '|' . $attempt, wp_salt('auth')),
            0,
            32
        );
    }

    private function seed(): string
    {
        $session = $this->session();
        if (!$session) {
            return '';
        }
        $seed = $session->get(self::SEED_KEY);
        if (!is_string($seed) || $seed === '') {
            $seed = bin2hex(random_bytes(16));
            $session->set(self::SEED_KEY, $seed);
        }

        return $seed;
    }

    private function attemptsSoFar(): int
    {
        $session = $this->session();

        return $session ? max(0, (int) $session->get(self::ATTEMPTS_KEY, 0)) : 0;
    }

    private function session(): ?\WC_Session
    {
        return function_exists('WC') && WC()->session instanceof \WC_Session ? WC()->session : null;
    }
}
