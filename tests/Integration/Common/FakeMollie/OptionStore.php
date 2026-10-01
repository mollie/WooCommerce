<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\FakeMollie;

/**
 * Persists the fake's state in one non-autoloaded option, so it survives across the separate HTTP
 * requests of a browser test. Test environments only.
 *
 * A page load fires several requests at once and each of them may talk to "Mollie", so the
 * read-modify-write of the option is serialised with a named database lock: load() takes it,
 * save() gives it back. A request that only reads keeps it until its connection closes, which is
 * the end of that request.
 */
final class OptionStore implements FakeMollieStore
{
    public const OPTION = 'mollie_fake_api_state';

    private const LOCK = 'mollie_fake_api_state';

    public function load(): array
    {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', self::LOCK));

        // The alloptions/notoptions caches are per request, but an object cache drop-in is not.
        wp_cache_delete(self::OPTION, 'options');
        $state = get_option(self::OPTION, []);

        return is_array($state) ? $state : [];
    }

    public function save(array $state): void
    {
        global $wpdb;
        update_option(self::OPTION, $state, false);
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::LOCK));
    }
}
