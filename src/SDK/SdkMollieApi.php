<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use InvalidArgumentException;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\MollieAddress;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use UnexpectedValueException;

final class SdkMollieApi implements MollieApi
{
    private int $callsMade = 0;

    public function __construct(
        private Api $api,
        private Settings $settings,
        private int $sessionLifetimeSeconds,
        private ?EventLog $log = null
    ) {
    }

    public function callsMade(): int
    {
        return $this->callsMade;
    }

    public function createSession(array $payload, string $idempotencyKey): ExpressSession
    {
        $client = $this->client();
        $client->setIdempotencyKey($idempotencyKey);

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $response = $this->sendLogged('POST', 'sessions', $idempotencyKey, static function () use ($client, $body) {
                return $client->performHttpCall('POST', 'sessions', $body);
            });
        } catch (ApiException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw MollieCallFailed::fromThrowable($exception);
        } finally {
            // The SDK resets the key only after a completed request.
            $client->resetIdempotencyKey();
        }

        try {
            return $this->toSession($response);
        } catch (UnexpectedValueException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw MollieCallFailed::fromThrowable($exception);
        }
    }

    public function session(string $sessionId): ExpressSession
    {
        // Mollie documents only the prefix; the id is encoded, so it stays one path segment.
        if (preg_match('/^sess_.+$/D', $sessionId) !== 1) {
            throw new InvalidArgumentException('Not a Mollie session id.');
        }

        $client = $this->client();
        $path = 'sessions/' . rawurlencode($sessionId);

        try {
            return $this->toSession($this->sendLogged('GET', $path, '', static function () use ($client, $path) {
                return $client->performHttpCall('GET', $path);
            }));
        } catch (ApiException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw MollieCallFailed::fromRead($exception);
        }
    }

    public function payment(string $paymentId): PaymentSnapshot
    {
        // Mollie documents only the prefix; the SDK encodes the id into the path.
        if (preg_match('/^tr_.+$/D', $paymentId) !== 1) {
            throw new InvalidArgumentException('Not a Mollie payment id.');
        }

        $client = $this->client();
        try {
            $payment = $this->sendLogged('GET', 'payments/' . $paymentId, '', static function () use ($client, $paymentId) {
                return $client->payments->get($paymentId);
            });
        } catch (ApiException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- only the code is read; the message is fixed
            throw MollieCallFailed::fromRead($exception);
        }
        $method = (string) ($payment->method ?? '');

        return new PaymentSnapshot(
            (string) $payment->id,
            (string) $payment->status,
            $method !== '' ? $method : null,
            Money::fromDecimal((string) $payment->amount->value, (string) $payment->amount->currency),
            mode: (string) ($payment->mode ?? 'live'),
            expressRef: $this->expressRef($payment->metadata ?? null),
            billingAddress: $this->address($payment->billingAddress ?? null),
            shippingAddress: $this->address($payment->shippingAddress ?? null)
        );
    }

    /**
     * @template T
     * @param callable(): T $request
     * @return T
     */
    private function sendLogged(string $method, string $path, string $idempotencyKey, callable $request)
    {
        $this->callsMade++;
        $started = microtime(true);
        $result = 'unreachable';
        try {
            $response = $request();
            $result = 'ok';

            return $response;
        } catch (ApiException $refused) {
            $result = $refused->getCode() >= 400 && $refused->getCode() < 500 ? 'refused' : 'unreachable';
            throw $refused;
        } finally {
            $fields = [
                'method' => $method,
                'path' => $path,
                'result' => $result,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ];
            if ($idempotencyKey !== '') {
                $fields['key'] = $idempotencyKey;
            }
            $this->log?->step('mollie.called', $fields);
        }
    }

    /**
     * @param mixed $metadata
     */
    private function expressRef($metadata): ?string
    {
        $ref = is_object($metadata) ? ($metadata->express_ref ?? null) : (is_array($metadata) ? ($metadata['express_ref'] ?? null) : null);

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

        return new ExpressSession(
            (string) $response->id,
            (string) $response->status,
            (string) ($response->clientAccessToken ?? ''),
            $this->expiresAt($response)
        );
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

        $createdAt = isset($response->createdAt) && is_string($response->createdAt)
            ? strtotime($response->createdAt)
            : false;

        return $createdAt === false ? '' : gmdate('c', $createdAt + $this->sessionLifetimeSeconds);
    }
}
