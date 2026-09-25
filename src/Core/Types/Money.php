<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

use InvalidArgumentException;

/**
 * A currency and an integer number of minor units.
 *
 * Mollie speaks decimal strings ('20.00') and checks line arithmetic to the cent, so no float may
 * appear anywhere between parsing and formatting. Amounts of different currencies are never mixed.
 */
final class Money
{
    /**
     * Currencies whose amounts carry no decimals. Every other currency has two.
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private function __construct(
        private int $minorUnits,
        private string $currency
    ) {
    }

    public static function fromMinorUnits(int $units, string $currency): self
    {
        return new self($units, self::normaliseCurrency($currency));
    }

    /**
     * @throws InvalidArgumentException When the string is not an amount in that currency's precision.
     */
    public static function fromDecimal(string $value, string $currency): self
    {
        $currency = self::normaliseCurrency($currency);
        $decimals = self::decimalsOf($currency);

        $pattern = $decimals === 0 ? '/^(-?)(\d+)$/' : '/^(-?)(\d+)(?:\.(\d{1,' . $decimals . '}))?$/';
        if (preg_match($pattern, $value, $matches) !== 1) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not a valid %s amount with %d decimals.', $value, $currency, $decimals)
            );
        }

        $fraction = str_pad($matches[3] ?? '', $decimals, '0');
        $units = (int) ($matches[2] . $fraction);

        return new self($matches[1] === '-' ? -$units : $units, $currency);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * The amount as Mollie writes it: a decimal string with the currency's own precision.
     */
    public function toDecimal(): string
    {
        $decimals = self::decimalsOf($this->currency);
        $sign = $this->minorUnits < 0 ? '-' : '';
        $digits = (string) abs($this->minorUnits);

        if ($decimals === 0) {
            return $sign . $digits;
        }

        $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);

        return $sign . substr($digits, 0, -$decimals) . '.' . substr($digits, -$decimals);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    /**
     * @return int -1, 0 or 1
     */
    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits <=> $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * The same currency and the same amount. Unlike equals(), a different currency answers false.
     */
    public function isSameAs(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorUnits === $other->minorUnits;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf('Amounts in %s and %s cannot be combined.', $this->currency, $other->currency)
            );
        }
    }

    private static function decimalsOf(string $currency): int
    {
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) ? 0 : 2;
    }

    private static function normaliseCurrency(string $currency): string
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not an ISO 4217 currency code.', $currency));
        }

        return $currency;
    }
}
