<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\SDK;

use InvalidArgumentException;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\MollieAddress;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use UnexpectedValueException;
final class SdkMollieApi implements \Mollie\WooCommerce\SDK\MollieApi
{
    public function __construct(private \Mollie\WooCommerce\SDK\Api $api, private Settings $settings, private int $sessionLifetimeSeconds)
    {
    }
    public function createSession(array $payload, string $idempotencyKey): ExpressSession
    {
        $client = $this->client();
        $client->setIdempotencyKey($idempotencyKey);
        try {
            $response = $client->performHttpCall('POST', 'sessions', json_encode($payload, \JSON_THROW_ON_ERROR));
        } catch (ApiException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw \Mollie\WooCommerce\SDK\MollieCallFailed::fromThrowable($exception);
        } finally {
            // The SDK resets the key only after a completed request.
            $client->resetIdempotencyKey();
        }
        try {
            return $this->toSession($response);
        } catch (UnexpectedValueException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw \Mollie\WooCommerce\SDK\MollieCallFailed::fromThrowable($exception);
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
        $method = (string) ($payment->method ?? '');
        return new PaymentSnapshot((string) $payment->id, (string) $payment->status, $method !== '' ? $method : null, Money::fromDecimal((string) $payment->amount->value, (string) $payment->amount->currency), mode: (string) ($payment->mode ?? 'live'), expressRef: $this->expressRef($payment->metadata ?? null), billingAddress: $this->address($payment->billingAddress ?? null), shippingAddress: $this->address($payment->shippingAddress ?? null));
    }
    /**
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
        return new ExpressSession((string) $response->id, (string) $response->status, (string) ($response->clientAccessToken ?? ''), $this->expiresAt($response));
    }
    /**
     * An open session carries no expiry field, so it is derived from createdAt.
     */
    private function expiresAt(object $response): string
    {
        foreach (['expiresAt', 'expiredAt'] as $field) {
            if (isset($response->{$field}) && is_string($response->{$field}) && $response->{$field} !== '') {
                return $response->{$field};
            }
        }
        $createdAt = isset($response->createdAt) && is_string($response->createdAt) ? strtotime($response->createdAt) : \false;
        return $createdAt === \false ? '' : gmdate('c', $createdAt + $this->sessionLifetimeSeconds);
    }
}
