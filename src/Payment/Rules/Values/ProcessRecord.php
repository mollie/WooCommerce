<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Rules\Values;

use InvalidArgumentException;

/**
 * What only the plugin knows about an order; never what Mollie knows.
 */
final class ProcessRecord
{
    public const VERSION = 1;

    private const CANCELLERS = ['cleanup', 'merchant', 'webhook'];

    private const SECTIONS = ['version', 'attempts', 'processed', 'inFlight', 'open', 'cancelledBy'];

    /**
     * @param list<array{id: string, origin: string, supersededBy: ?string}> $attempts
     * @param list<string> $processed "<mollie id>[:<status>]"
     * @param list<array{kind: string, idempotencyKey: string, amount: array{value: string, currency: string}, by: string}> $inFlight
     * @param list<array{question: string, mollieId: string}> $open
     */
    private function __construct(
        private array $attempts,
        private array $processed,
        private array $inFlight,
        private array $open,
        private ?string $cancelledBy
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], [], [], null);
    }

    /**
     * A missing or older version reads as empty.
     *
     * @param array<mixed> $stored
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $stored): self
    {
        $version = $stored['version'] ?? null;
        if ($version === null) {
            return self::empty();
        }
        if (!is_int($version)) {
            throw new InvalidArgumentException('The process record version is not a number.');
        }
        if ($version < self::VERSION) {
            return self::empty();
        }
        if ($version > self::VERSION) {
            throw new InvalidArgumentException(sprintf('Process record version %d is newer than this code knows.', $version));
        }

        $unknown = array_diff(array_keys($stored), self::SECTIONS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('The process record has no section "%s".', implode('", "', $unknown)));
        }

        return new self(
            self::attemptsFrom($stored),
            self::processedFrom($stored),
            self::inFlightFrom($stored),
            self::openFrom($stored),
            self::cancelledByFrom($stored)
        );
    }

    /**
     * @return array{version: int, attempts: list<array<string, mixed>>, processed: list<string>, inFlight: list<array<string, mixed>>, open: list<array<string, string>>, cancelledBy: ?string}
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'attempts' => $this->attempts,
            'processed' => $this->processed,
            'inFlight' => $this->inFlight,
            'open' => $this->open,
            'cancelledBy' => $this->cancelledBy,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withCancelledBy(string $cancelledBy): self
    {
        self::assertCanceller($cancelledBy);
        $copy = clone $this;
        $copy->cancelledBy = $cancelledBy;

        return $copy;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withProcessed(string $event): self
    {
        if (!self::isText($event)) {
            throw new InvalidArgumentException('A processed entry is a non-empty Mollie event id.');
        }
        $copy = clone $this;
        if (!in_array($event, $copy->processed, true)) {
            $copy->processed[] = $event;
        }

        return $copy;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withOpen(string $question, string $mollieId): self
    {
        if (!self::isText($question) || !self::isText($mollieId)) {
            throw new InvalidArgumentException('An open question names a question and a Mollie id.');
        }
        $entry = ['question' => $question, 'mollieId' => $mollieId];
        $copy = clone $this;
        if (!in_array($entry, $copy->open, true)) {
            $copy->open[] = $entry;
        }

        return $copy;
    }

    public function cancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    /**
     * @return list<string>
     */
    public function processed(): array
    {
        return $this->processed;
    }

    /**
     * @return list<array{question: string, mollieId: string}>
     */
    public function open(): array
    {
        return $this->open;
    }

    /**
     * @param array<mixed> $stored
     * @return list<array{id: string, origin: string, supersededBy: ?string}>
     */
    private static function attemptsFrom(array $stored): array
    {
        $attempts = [];
        foreach (self::listOf($stored, 'attempts') as $entry) {
            $entry = self::entry($entry, 'attempts', ['id', 'origin'], ['supersededBy']);
            $supersededBy = $entry['supersededBy'] ?? null;
            if ($supersededBy !== null && !self::isText($supersededBy)) {
                throw new InvalidArgumentException('An attempt is superseded by a Mollie id or by nothing.');
            }
            $attempts[] = ['id' => $entry['id'], 'origin' => $entry['origin'], 'supersededBy' => $supersededBy];
        }

        return $attempts;
    }

    /**
     * @param array<mixed> $stored
     * @return list<string>
     */
    private static function processedFrom(array $stored): array
    {
        $processed = [];
        foreach (self::listOf($stored, 'processed') as $event) {
            if (!self::isText($event)) {
                throw new InvalidArgumentException('A processed entry is a non-empty Mollie event id.');
            }
            $processed[] = $event;
        }

        return $processed;
    }

    /**
     * @param array<mixed> $stored
     * @return list<array{kind: string, idempotencyKey: string, amount: array{value: string, currency: string}, by: string}>
     */
    private static function inFlightFrom(array $stored): array
    {
        $inFlight = [];
        foreach (self::listOf($stored, 'inFlight') as $entry) {
            $entry = self::entry($entry, 'inFlight', ['kind', 'idempotencyKey', 'by'], [], ['amount']);
            $inFlight[] = [
                'kind' => $entry['kind'],
                'idempotencyKey' => $entry['idempotencyKey'],
                'amount' => self::commandAmount($entry['amount']),
                'by' => $entry['by'],
            ];
        }

        return $inFlight;
    }

    /**
     * @param array<mixed> $stored
     * @return list<array{question: string, mollieId: string}>
     */
    private static function openFrom(array $stored): array
    {
        $open = [];
        foreach (self::listOf($stored, 'open') as $entry) {
            $entry = self::entry($entry, 'open', ['question', 'mollieId']);
            $open[] = ['question' => $entry['question'], 'mollieId' => $entry['mollieId']];
        }

        return $open;
    }

    /**
     * @param array<mixed> $stored
     */
    private static function cancelledByFrom(array $stored): ?string
    {
        $cancelledBy = $stored['cancelledBy'] ?? null;
        if ($cancelledBy !== null) {
            self::assertCanceller($cancelledBy);
        }

        return $cancelledBy;
    }

    /**
     * @param array<mixed> $stored
     * @return list<mixed>
     */
    private static function listOf(array $stored, string $section): array
    {
        $list = $stored[$section] ?? [];
        if (!is_array($list) || array_values($list) !== $list) {
            throw new InvalidArgumentException(sprintf('The process record section "%s" is not a list.', $section));
        }

        return $list;
    }

    /**
     * @param list<string> $requiredText
     * @param list<string> $optional
     * @param list<string> $requiredNested
     * @return array<string, mixed>
     */
    private static function entry(
        mixed $entry,
        string $section,
        array $requiredText,
        array $optional = [],
        array $requiredNested = []
    ): array {

        if (!is_array($entry)) {
            throw new InvalidArgumentException(sprintf('An entry of "%s" is not a map.', $section));
        }
        $unknown = array_diff(array_keys($entry), $requiredText, $optional, $requiredNested);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('"%s" has no field "%s".', $section, implode('", "', $unknown)));
        }
        foreach ($requiredText as $field) {
            if (!self::isText($entry[$field] ?? null)) {
                throw new InvalidArgumentException(sprintf('"%s.%s" must be a non-empty string.', $section, $field));
            }
        }
        foreach ($requiredNested as $field) {
            if (!array_key_exists($field, $entry)) {
                throw new InvalidArgumentException(sprintf('"%s.%s" is missing.', $section, $field));
            }
        }

        return $entry;
    }

    /**
     * @return array{value: string, currency: string}
     */
    private static function commandAmount(mixed $amount): array
    {
        if (!is_array($amount) || array_diff(array_keys($amount), ['value', 'currency']) !== []) {
            throw new InvalidArgumentException('A command amount holds a value and a currency only.');
        }
        $value = $amount['value'] ?? null;
        $currency = $amount['currency'] ?? null;
        if (!is_string($value) || preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException('A command amount value is a decimal string.');
        }
        if (!is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('A command amount currency is an ISO 4217 code.');
        }

        return ['value' => $value, 'currency' => $currency];
    }

    private static function assertCanceller(mixed $cancelledBy): void
    {
        if (!in_array($cancelledBy, self::CANCELLERS, true)) {
            throw new InvalidArgumentException('An order is cancelled by cleanup, the merchant or a webhook.');
        }
    }

    private static function isText(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
