<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\FakeMollie;

/**
 * The documented validation rules of POST /v2/sessions, as a pure function.
 *
 * Source: the "Create Checkout Session" API reference summarised in
 * reqs/express-component-research.md §2.2. Mollie answers 422 on any deviation, and nobody on the
 * project has seen a real response yet, so this is the strictest reading of the documentation:
 * a payload that passes here may still be refused by the beta, but a payload that fails here
 * would certainly be refused.
 *
 * Amounts are compared in minor units. Comparing the decimal strings as floats is exactly the
 * rounding bug this class exists to catch in the plugin.
 */
final class SessionRules
{
    private const LINE_TYPES = [
        'physical',
        'digital',
        'shipping_fee',
        'discount',
        'store_credit',
        'gift_card',
        'surcharge',
    ];

    private const NEGATIVE_TYPES = ['discount', 'store_credit', 'gift_card'];

    private const CUSTOMER_DETAILS = ['email', 'billing-address', 'shipping-address'];

    private const ZERO_DECIMAL_CURRENCIES = ['JPY', 'ISK'];

    /**
     * @param array<string, mixed> $payload The decoded request body.
     * @return array<int, array{field: string, message: string}> Empty when the payload is valid.
     */
    public static function violations(array $payload): array
    {
        $violations = [];

        foreach (['amount', 'description', 'lines', 'redirectUrl'] as $required) {
            if (!isset($payload[$required]) || $payload[$required] === '' || $payload[$required] === []) {
                $violations[] = self::violation($required, "The '{$required}' field is required.");
            }
        }
        if ($violations) {
            return $violations;
        }

        // The plugin authenticates with an API key only; both fields are reserved for
        // organisation-level credentials and refused alongside a key.
        foreach (['profileId', 'testmode'] as $forbidden) {
            if (array_key_exists($forbidden, $payload)) {
                $violations[] = self::violation(
                    $forbidden,
                    "The '{$forbidden}' field must not be sent when authenticating with an API key."
                );
            }
        }

        $currency = (string) ($payload['amount']['currency'] ?? '');
        $amountViolation = self::amountViolation('amount', $payload['amount'], $currency);
        if ($amountViolation) {
            return array_merge($violations, [$amountViolation]);
        }

        if (!is_array($payload['lines'])) {
            return array_merge($violations, [self::violation('lines', 'Lines must be a list.')]);
        }

        $sum = 0;
        foreach (array_values($payload['lines']) as $index => $line) {
            $lineViolations = self::lineViolations("lines.{$index}", (array) $line, $currency);
            $violations = array_merge($violations, $lineViolations);
            if (!$lineViolations) {
                $sum += self::minor($line['totalAmount']['value']);
            }
        }

        if (!$violations && $sum !== self::minor($payload['amount']['value'])) {
            $violations[] = self::violation(
                'amount',
                'The amount does not match the sum of the lines.'
            );
        }

        return array_merge($violations, self::optionalFieldViolations($payload));
    }

    /**
     * The vatAmount Mollie expects for a line, in the decimal string form the API uses.
     */
    public static function expectedVatAmount(string $totalAmount, string $vatRate): string
    {
        $rate = (float) $vatRate;
        $vat = self::minor($totalAmount) * ($rate / (100 + $rate));

        return number_format(round($vat) / 100, 2, '.', '');
    }

    /**
     * @param array<string, mixed> $line
     * @return array<int, array{field: string, message: string}>
     */
    private static function lineViolations(string $path, array $line, string $currency): array
    {
        $violations = [];

        foreach (['description', 'quantity', 'unitPrice', 'totalAmount'] as $required) {
            if (!isset($line[$required])) {
                $violations[] = self::violation("{$path}.{$required}", "The '{$required}' field is required.");
            }
        }
        if ($violations) {
            return $violations;
        }

        $type = $line['type'] ?? 'physical';
        if (!in_array($type, self::LINE_TYPES, true)) {
            $violations[] = self::violation("{$path}.type", "The line type '{$type}' is not allowed when creating a session.");
        }

        if (!is_int($line['quantity']) || $line['quantity'] < 1) {
            $violations[] = self::violation("{$path}.quantity", 'The quantity must be an integer of at least 1.');
        }

        foreach (['unitPrice', 'totalAmount', 'discountAmount', 'vatAmount'] as $money) {
            if (!isset($line[$money])) {
                continue;
            }
            $violation = self::amountViolation("{$path}.{$money}", $line[$money], $currency, true);
            if ($violation) {
                $violations[] = $violation;
            }
        }
        if ($violations) {
            return $violations;
        }

        $unitPrice = self::minor($line['unitPrice']['value']);
        $total = self::minor($line['totalAmount']['value']);
        $discount = isset($line['discountAmount']) ? self::minor($line['discountAmount']['value']) : 0;

        if (in_array($type, self::NEGATIVE_TYPES, true) && $unitPrice >= 0) {
            $violations[] = self::violation("{$path}.unitPrice", "The unit price of a '{$type}' line must be negative.");
        }
        if ($discount < 0) {
            $violations[] = self::violation("{$path}.discountAmount", 'The discount amount must be positive.');
        }
        if ($total !== $unitPrice * (int) $line['quantity'] - $discount) {
            $violations[] = self::violation(
                "{$path}.totalAmount",
                'The total amount must equal (unitPrice x quantity) - discountAmount.'
            );
        }

        if (isset($line['vatRate'])) {
            if (!is_string($line['vatRate']) || !preg_match('/^\d+\.\d{2}$/', $line['vatRate'])) {
                $violations[] = self::violation("{$path}.vatRate", "The VAT rate must be a string such as '21.00'.");
            } elseif (isset($line['vatAmount'])) {
                $expected = self::expectedVatAmount($line['totalAmount']['value'], $line['vatRate']);
                if (self::minor($expected) !== self::minor($line['vatAmount']['value'])) {
                    $violations[] = self::violation(
                        "{$path}.vatAmount",
                        "The VAT amount must equal totalAmount x (vatRate / (100 + vatRate)); expected {$expected}."
                    );
                }
            }
        }

        if (isset($line['sku']) && strlen((string) $line['sku']) > 64) {
            $violations[] = self::violation("{$path}.sku", 'The SKU may be at most 64 characters.');
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{field: string, message: string}>
     */
    private static function optionalFieldViolations(array $payload): array
    {
        $violations = [];

        if (isset($payload['requiredCustomerDetails'])) {
            $details = $payload['requiredCustomerDetails'];
            $valid = is_array($details)
                && count($details) === count(array_unique($details))
                && !array_diff($details, self::CUSTOMER_DETAILS);
            if (!$valid) {
                $violations[] = self::violation(
                    'requiredCustomerDetails',
                    'Must be a list of unique values out of: ' . implode(', ', self::CUSTOMER_DETAILS) . '.'
                );
            }
        }

        if (isset($payload['metadata']) && strlen((string) json_encode($payload['metadata'])) > 1024) {
            $violations[] = self::violation('metadata', 'The metadata may be at most about 1kB.');
        }

        if (isset($payload['customerId']) && !preg_match('/^cst_.+$/', (string) $payload['customerId'])) {
            $violations[] = self::violation('customerId', 'The customer id is invalid.');
        }
        if (($payload['sequenceType'] ?? 'oneoff') === 'first' && empty($payload['customerId'])) {
            $violations[] = self::violation('customerId', "A customer id is required when sequenceType is 'first'.");
        }

        foreach (['redirectUrl' => $payload['redirectUrl'] ?? null, 'payment.webhookUrl' => $payload['payment']['webhookUrl'] ?? null] as $field => $url) {
            if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
                $violations[] = self::violation($field, "The '{$field}' is not a valid URL.");
            }
        }

        foreach (['billingAddress', 'shippingAddress'] as $address) {
            $phone = $payload[$address]['phone'] ?? null;
            if ($phone !== null && !preg_match('/^\+[1-9]\d{1,14}$/', (string) $phone)) {
                $violations[] = self::violation("{$address}.phone", 'The phone number must be in E.164 format.');
            }
        }

        return $violations;
    }

    /**
     * @param mixed $amount
     * @return array{field: string, message: string}|null
     */
    private static function amountViolation(string $path, $amount, string $currency, bool $allowNegative = false): ?array
    {
        if (!is_array($amount) || !isset($amount['currency'], $amount['value'])) {
            return self::violation($path, 'An amount needs a currency and a value.');
        }
        if (!is_string($amount['value'])) {
            return self::violation($path, 'The amount value must be a string.');
        }
        if ($amount['currency'] !== $currency || !preg_match('/^[A-Z]{3}$/', $currency)) {
            return self::violation($path, 'All amounts must use the currency of the session.');
        }

        $decimals = in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) ? '' : '\.\d{2}';
        $sign = $allowNegative ? '-?' : '';
        if (!preg_match('/^' . $sign . '\d+' . $decimals . '$/', $amount['value'])) {
            return self::violation($path, "The amount value '{$amount['value']}' has the wrong number of decimals for {$currency}.");
        }

        return null;
    }

    private static function minor(string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /**
     * @return array{field: string, message: string}
     */
    private static function violation(string $field, string $message): array
    {
        return ['field' => $field, 'message' => $message];
    }
}
