<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Webhooks;

/**
 * Owns the mollie_webhook_secret option: generates it on first use and
 * verifies incoming values against it.
 *
 * Extracted so every place that needs the secret (building the webhook URL,
 * building the test-webhook URL) goes through getOrCreate() instead of reading
 * the option directly - reading it directly returns '' before the secret has
 * ever been generated.
 */
class WebhookSecret
{
    private const OPTION_NAME = 'mollie_webhook_secret';
    public function getOrCreate(): string
    {
        $secret = get_option(self::OPTION_NAME, '');
        if (!$secret) {
            $secret = wp_generate_password(32, \false);
            update_option(self::OPTION_NAME, $secret);
        }
        return $secret;
    }
    public function check(?string $incoming): bool
    {
        if (!$incoming) {
            return \false;
        }
        $stored = $this->getOrCreate();
        if (!$stored) {
            return \false;
        }
        return hash_equals($stored, $incoming);
    }
}
