<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Doubles;

/**
 * Marked secrets and personal data for the leak checks (blueprint FF-16, REQ-G1, REQ-G6).
 *
 * Every value carries the marker, so one search over a log or over a browser-bound response
 * finds any of them. The values are still valid for their field — the API key matches the
 * plugin's key pattern, the email is an email, the phone is E.164 — so they travel through the
 * real validation and the real request builders instead of being rejected at the door.
 */
final class CanaryData
{
    public const MARKER = 'CANARY7f3a';

    public const LIVE_API_KEY = 'live_CANARY7f3aKeyXXXXXXXXXXXXXXXXXXXXX';
    public const TEST_API_KEY = 'test_CANARY7f3aKeyXXXXXXXXXXXXXXXXXXXXX';
    public const WEBHOOK_SECRET = 'CANARY7f3aWebhookSecretXXXXXXXXX';

    public const EMAIL = 'shopper.CANARY7f3a@example.org';
    public const GIVEN_NAME = 'PietCANARY7f3a';
    public const FAMILY_NAME = 'MondriaanCANARY7f3a';
    public const STREET = 'CANARY7f3a-straat 12';
    public const STREET_ADDITIONAL = 'Unit CANARY7f3a';
    public const PHONE = '+31208202070';

    /**
     * A Mollie-shaped address, as the Express Component would hand it back on the payment.
     *
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public static function mollieAddress(array $overrides = []): array
    {
        return array_merge([
            'givenName' => self::GIVEN_NAME,
            'familyName' => self::FAMILY_NAME,
            'email' => self::EMAIL,
            'phone' => self::PHONE,
            'streetAndNumber' => self::STREET,
            'streetAdditional' => self::STREET_ADDITIONAL,
            'postalCode' => '1015 CS',
            'city' => 'Amsterdam',
            'region' => 'Noord-Holland',
            'country' => 'NL',
        ], $overrides);
    }

    /**
     * Whether any marked value — or the unmarked phone number — appears in the haystack.
     */
    public static function leakedIn(string $haystack): bool
    {
        return strpos($haystack, self::MARKER) !== false || strpos($haystack, self::PHONE) !== false;
    }
}
