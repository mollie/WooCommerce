<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Rules\Values;

use InvalidArgumentException;
/**
 * What only the plugin knows about an order; never what Mollie knows.
 */
final class ProcessRecord
{
    public const VERSION = 1;
    private const CANCELLERS = ['cleanup', 'merchant', 'webhook'];
    private const SECTIONS = ['version', 'processed', 'open', 'cancelledBy'];
    /**
     * @param list<string> $processed "<mollie id>[:<status>]"
     * @param list<array{question: string, mollieId: string}> $open
     */
    private function __construct(private array $processed, private array $open, private ?string $cancelledBy)
    {
    }
    public static function empty(): self
    {
        return new self([], [], null);
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
        return new self(self::processedFrom($stored), self::openFrom($stored), self::cancelledByFrom($stored));
    }
    /**
     * @return array{version: int, processed: list<string>, open: list<array<string, string>>, cancelledBy: ?string}
     */
    public function toArray(): array
    {
        return ['version' => self::VERSION, 'processed' => $this->processed, 'open' => $this->open, 'cancelledBy' => $this->cancelledBy];
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
        if (!in_array($event, $copy->processed, \true)) {
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
        if (!in_array($entry, $copy->open, \true)) {
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
     * @return list<array{question: string, mollieId: string}>
     */
    private static function openFrom(array $stored): array
    {
        $open = [];
        foreach (self::listOf($stored, 'open') as $entry) {
            if (!is_array($entry) || array_diff(array_keys($entry), ['question', 'mollieId']) !== []) {
                throw new InvalidArgumentException('An open question holds a question and a Mollie id only.');
            }
            if (!self::isText($entry['question'] ?? null) || !self::isText($entry['mollieId'] ?? null)) {
                throw new InvalidArgumentException('An open question names a question and a Mollie id.');
            }
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
    private static function assertCanceller(mixed $cancelledBy): void
    {
        if (!in_array($cancelledBy, self::CANCELLERS, \true)) {
            throw new InvalidArgumentException('An order is cancelled by cleanup, the merchant or a webhook.');
        }
    }
    private static function isText(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
