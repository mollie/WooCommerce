<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\Mollie;

use InvalidArgumentException;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Core\Types\ExpressSession;
use Mollie\WooCommerce\Core\Types\MollieAddress;
use Mollie\WooCommerce\Core\Types\Money;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use UnexpectedValueException;
/**
 * The single reader of the Mollie API key and the single place a mutating Mollie call is made
 * (blueprint chokepoint 2, ADR-012 and ADR-013).
 *
 * The key is resolved here and never handed on. Every mutating call sets its idempotency key on
 * the client first, and is sent raw because the typed sessions endpoint of SDK v2.79 drops
 * clientAccessToken. A failed session create surfaces as MollieCallFailed, classified, without the
 * SDK's text, which contains Mollie's response body.
 */
final class SdkMollieApi implements \Mollie\WooCommerce\Adapter\Mollie\MollieApi
{
    private Api $api;
    private Settings $settings;
    public function __construct(Api $api, Settings $settings)
    {
        $this->api = $api;
        $this->settings = $settings;
    }
    public function createSession(array $payload, string $idempotencyKey): ExpressSession
    {
        $client = $this->client();
        $client->setIdempotencyKey($idempotencyKey);
        try {
            $response = $client->performHttpCall('POST', 'sessions', json_encode($payload, \JSON_THROW_ON_ERROR));
        } catch (ApiException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw \Mollie\WooCommerce\Adapter\Mollie\MollieCallFailed::fromThrowable($exception);
        } finally {
            // The SDK only resets the key after a completed request; do not let it leak into the next call.
            $client->resetIdempotencyKey();
        }
        try {
            return $this->toSession($response);
        } catch (UnexpectedValueException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw \Mollie\WooCommerce\Adapter\Mollie\MollieCallFailed::fromThrowable($exception);
        }
    }
    public function session(string $sessionId): ExpressSession
    {
        if (preg_match('/^sess_[A-Za-z0-9]+$/', $sessionId) !== 1) {
            throw new InvalidArgumentException('Not a Mollie session id.');
        }
        return $this->toSession($this->client()->performHttpCall('GET', 'sessions/' . $sessionId));
    }
    public function payment(string $paymentId): PaymentSnapshot
    {
        if (preg_match('/^tr_[A-Za-z0-9]+$/', $paymentId) !== 1) {
            throw new InvalidArgumentException('Not a Mollie payment id.');
        }
        $payment = $this->client()->payments->get($paymentId);
        // A payment that has not been paid with anything yet has no method.
        $method = (string) ($payment->method ?? '');
        return new PaymentSnapshot((string) $payment->id, (string) $payment->status, $method !== '' ? $method : null, Money::fromDecimal((string) $payment->amount->value, (string) $payment->amount->currency), $payment, mode: (string) ($payment->mode ?? 'live'), expressRef: $this->expressRef($payment->metadata ?? null), billingAddress: $this->address($payment->billingAddress ?? null), shippingAddress: $this->address($payment->shippingAddress ?? null));
    }
    /**
     * metadata.express_ref, which a payment created from an express session inherits.
     *
     * @param mixed $metadata
     */
    private function expressRef($metadata): ?string
    {
        $ref = is_object($metadata) ? $metadata->express_ref ?? null : (is_array($metadata) ? $metadata['express_ref'] ?? null : null);
        return is_string($ref) && $ref !== '' ? $ref : null;
    }
    /**
     * @param mixed $address
     */
    private function address($address): ?MollieAddress
    {
        if (!is_object($address) && !is_array($address)) {
            return null;
        }
        return MollieAddress::fromArray(is_object($address) ? get_object_vars($address) : $address);
    }
    private function client(): MollieApiClient
    {
        return $this->api->getApiClient((string) $this->settings->getApiKey());
    }
    /**
     * @param mixed $response
     */
    private function toSession($response): ExpressSession
    {
        if (!is_object($response) || !isset($response->id, $response->status)) {
            throw new UnexpectedValueException('Mollie answered a session request with something that is not a session.');
        }
        return new ExpressSession((string) $response->id, (string) $response->status, (string) ($response->clientAccessToken ?? ''), (string) ($response->expiresAt ?? ''), $response);
    }
}
