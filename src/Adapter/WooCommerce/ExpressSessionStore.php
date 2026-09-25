<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WooCommerce;

/**
 * The shopper's express session, kept in the WooCommerce session and nowhere else (REQ-G2).
 *
 * The clientAccessToken is a per-shopper credential: it is never written to an option, a transient,
 * order meta or a log. Beside the open session, the store counts the sessions created for this
 * shopper, so every new session gets a new attempt number and therefore a new idempotency key,
 * while a retry after a failure, which records nothing, keeps the same one.
 */
class ExpressSessionStore
{
    private const SESSION_KEY = 'mollie_express_session';
    private const ATTEMPTS_KEY = 'mollie_express_session_attempts';
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
        return ['id' => (string) $stored['id'], 'token' => (string) $stored['token'], 'expiresAt' => (int) $stored['expiresAt'], 'fingerprint' => (string) $stored['fingerprint'], 'ref' => (string) $stored['ref'], 'attempt' => (int) $stored['attempt']];
    }
    public function remember(string $id, string $token, int $expiresAt, string $fingerprint, string $ref, int $attempt): void
    {
        $session = $this->session();
        if (!$session) {
            return;
        }
        $session->set(self::SESSION_KEY, ['id' => $id, 'token' => $token, 'expiresAt' => $expiresAt, 'fingerprint' => $fingerprint, 'ref' => $ref, 'attempt' => $attempt]);
        $session->set(self::ATTEMPTS_KEY, max($attempt, $this->attemptsSoFar()));
    }
    public function forget(): void
    {
        $session = $this->session();
        if ($session) {
            $session->set(self::SESSION_KEY, null);
        }
    }
    /**
     * The attempt number the next new session is created under.
     */
    public function nextAttempt(): int
    {
        return $this->attemptsSoFar() + 1;
    }
    /**
     * Who the shopper is, for an idempotency key: a hash, never the WooCommerce customer id itself.
     */
    public function customerKey(): string
    {
        $session = $this->session();
        return hash_hmac('sha256', $session ? (string) $session->get_customer_id() : '', wp_salt('nonce'));
    }
    /**
     * The unguessable reference a session carries in place of an order id. Derived, not random, so a
     * retry of the same attempt sends the same body under the same idempotency key.
     */
    public function expressRef(string $fingerprint, int $attempt): string
    {
        return 'exr_' . substr(hash_hmac('sha256', $this->customerKey() . '|' . $fingerprint . '|' . $attempt, wp_salt('auth')), 0, 32);
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
