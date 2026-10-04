<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\Shared\Values\Money;
/**
 * Mollie refuses lines unless totalAmount = unitPrice x quantity - discountAmount,
 * vatAmount = totalAmount x vatRate / (100 + vatRate), and the lines sum to the amount.
 * Discount lines carry no VAT rate: a coupon can span products with different rates.
 */
final class SessionLines
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function fromCart(CartFacts $cart): array
    {
        $currency = self::currencyOf($cart);
        if ($currency === null) {
            return [];
        }
        $lines = self::rawLines($cart);
        $lines = self::putRoundingResidueOnLargestLine($lines, $cart->total());
        return array_map(static fn(array $line): array => self::render($line, $currency), $lines);
    }
    /**
     * @return list<array{type: string, description: string, quantity: int, total: int, vatRate: ?string}>
     */
    private static function rawLines(CartFacts $cart): array
    {
        $lines = [];
        foreach ($cart->lines() as $line) {
            $subtotal = $line->subtotal();
            $lines[] = self::raw('physical', $line->name() !== '' ? $line->name() : 'Product', max(1, $line->quantity()), $subtotal === null ? 0 : $subtotal->minorUnits(), $line->vatRate());
        }
        foreach ($cart->fees() as $fee) {
            $amount = $fee->amount()->minorUnits();
            $lines[] = $amount < 0 ? self::raw('discount', $fee->name() !== '' ? $fee->name() : 'Discount', 1, $amount, null) : self::raw('surcharge', $fee->name() !== '' ? $fee->name() : 'Fee', 1, $amount, $fee->vatRate());
        }
        $shipping = $cart->shipping();
        if ($shipping !== null) {
            $lines[] = self::raw('shipping_fee', $shipping->label() !== '' ? $shipping->label() : 'Shipping', 1, $shipping->cost()->minorUnits(), $shipping->vatRate());
        }
        foreach ($cart->coupons() as $coupon) {
            $lines[] = self::raw('discount', $coupon->code(), 1, -abs($coupon->amount()->minorUnits()), null);
        }
        return $lines;
    }
    /**
     * @return array{type: string, description: string, quantity: int, total: int, vatRate: ?string}
     */
    private static function raw(string $type, string $description, int $quantity, int $total, ?string $vatRate): array
    {
        return ['type' => $type, 'description' => $description, 'quantity' => $quantity, 'total' => $total, 'vatRate' => $vatRate];
    }
    /**
     * @param list<array{type: string, description: string, quantity: int, total: int, vatRate: ?string}> $lines
     * @return list<array{type: string, description: string, quantity: int, total: int, vatRate: ?string}>
     */
    private static function putRoundingResidueOnLargestLine(array $lines, ?Money $total): array
    {
        if ($total === null || $lines === []) {
            return $lines;
        }
        $residual = $total->minorUnits() - array_sum(array_column($lines, 'total'));
        if ($residual === 0) {
            return $lines;
        }
        $largest = 0;
        foreach ($lines as $index => $line) {
            if ($line['total'] > $lines[$largest]['total']) {
                $largest = $index;
            }
        }
        $lines[$largest]['total'] += $residual;
        return $lines;
    }
    /**
     * A total the quantity does not divide gets the next unit price up and the excess as discountAmount.
     *
     * @param array{type: string, description: string, quantity: int, total: int, vatRate: ?string} $line
     * @return array<string, mixed>
     */
    private static function render(array $line, string $currency): array
    {
        $quantity = $line['quantity'];
        $total = $line['total'];
        $unit = intdiv($total, $quantity);
        if ($unit * $quantity < $total) {
            $unit++;
        }
        $discount = $unit * $quantity - $total;
        $rendered = ['type' => $line['type'], 'description' => $line['description'], 'quantity' => $quantity, 'unitPrice' => self::amount($unit, $currency), 'totalAmount' => self::amount($total, $currency)];
        if ($discount > 0) {
            $rendered['discountAmount'] = self::amount($discount, $currency);
        }
        if ($line['vatRate'] !== null) {
            $rendered['vatRate'] = $line['vatRate'];
            $rendered['vatAmount'] = self::amount(self::vatOf($total, $line['vatRate']), $currency);
        }
        return $rendered;
    }
    private static function vatOf(int $total, string $vatRate): int
    {
        $rate = (float) $vatRate;
        return (int) round($total * ($rate / (100 + $rate)));
    }
    /**
     * @return array{currency: string, value: string}
     */
    private static function amount(int $minorUnits, string $currency): array
    {
        return ['currency' => $currency, 'value' => Money::fromMinorUnits($minorUnits, $currency)->toDecimal()];
    }
    private static function currencyOf(CartFacts $cart): ?string
    {
        if ($cart->total() !== null) {
            return $cart->total()->currency();
        }
        foreach ($cart->lines() as $line) {
            if ($line->subtotal() !== null) {
                return $line->subtotal()->currency();
            }
        }
        return null;
    }
}
