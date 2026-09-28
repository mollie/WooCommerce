<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use InvalidArgumentException;

/**
 * The outcome of the Mollie connection check.
 *
 * A failure names the stage that failed, because the code alone cannot: the environment and API
 * key checks never produce an HTTP status, so they would look like a request that never reached
 * Mollie. The code is Mollie's HTTP status, or 0 when there is none.
 */
final class ConnectionResult
{
    public const KIND_INCOMPATIBLE = 'incompatible';
    public const KIND_API_KEY = 'api_key';
    public const KIND_API = 'api';

    private const KINDS = [self::KIND_INCOMPATIBLE, self::KIND_API_KEY, self::KIND_API];

    private bool $connected;
    private ?string $errorKind;
    private int $errorCode;
    private string $errorMessage;

    private function __construct(bool $connected, ?string $errorKind, int $errorCode, string $errorMessage)
    {
        $this->connected = $connected;
        $this->errorKind = $errorKind;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public static function connected(): self
    {
        return new self(true, null, 0, '');
    }

    /**
     * @param string $kind One of the KIND_* constants.
     * @param string $message Plugin-authored text may carry markup; Mollie's text is not escaped yet.
     *
     * @throws InvalidArgumentException For a kind that is not one of the KIND_* constants.
     */
    public static function failed(string $kind, int $code, string $message): self
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('Unknown connection failure kind; use a KIND_* constant.');
        }

        return new self(false, $kind, $code, $message);
    }

    /**
     * For code that only knows whether the check passed: a failure without detail reads as a key
     * problem, which renders the long-standing "check your API keys" message.
     */
    public static function fromStatus(bool $connected): self
    {
        return $connected ? self::connected() : self::failed(self::KIND_API_KEY, 0, '');
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Null when connected.
     */
    public function errorKind(): ?string
    {
        return $this->errorKind;
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }

    public function errorMessage(): string
    {
        return $this->errorMessage;
    }
}
