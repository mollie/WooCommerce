<?php
declare(strict_types=1);

define('INTEGRATION_TESTS_ROOT_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(INTEGRATION_TESTS_ROOT_DIR, 1));

if (file_exists(ROOT_DIR . '/.env.integration')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR, '.env.integration');
    $dotenv->load();
}

/**
 * Stops the run with a non-zero exit code, so a broken setup can never look like a green suite.
 */
$abort = static function (string $message): void {
    fwrite(STDERR, PHP_EOL . 'Integration tests cannot start: ' . $message . PHP_EOL);
    exit(1);
};

// Without configuration the suite runs against the DDEV site of this repository.
$wpRootDir = str_replace(
    '${ROOT_DIR}',
    ROOT_DIR,
    (string) ($_ENV['MOLLIE_INTEGRATION_WP_DIR'] ?? ROOT_DIR . '/.ddev/wordpress')
);

if (!file_exists($wpRootDir . '/wp-load.php')) {
    $abort(
        "no WordPress found in {$wpRootDir}. Run the suite inside DDEV (composer tests:integration)"
        . ' or set MOLLIE_INTEGRATION_WP_DIR in .env.integration (see .env.integration.example).'
    );
}

define('WP_ROOT_DIR', $wpRootDir);

$_SERVER['HTTP_HOST'] = ''; // just to avoid a warning

// No background request for cron: nothing is scheduled to run during the suite.
if (!defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', true);
}

// Set a constant that your plugin can check to prevent double initialization
define('MOLLIE_INTEGRATION_TESTS', true);
require_once WP_ROOT_DIR . '/wp-load.php';
require_once WP_ROOT_DIR . '/wp-includes/functions.php';

if (!class_exists(WooCommerce::class)) {
    $abort('WooCommerce is not active on the test site.');
}
if (!class_exists(WC_Subscriptions::class)) {
    fwrite(STDERR, 'Notice: WooCommerce Subscriptions is not active; the subscription cases are skipped.' . PHP_EOL);
}

/**
 * Nothing leaves the machine. A test answers the requests it expects with its own
 * pre_http_request filter (FakeMollieTransport does); whatever nobody answered is refused here
 * and listed at the end of the run. No Mollie API key is needed, and a stored one is never sent.
 */
$GLOBALS['mollie_integration_blocked_requests'] = [];
add_filter('pre_http_request', static function ($preempt, $args, $url) {
    if ($preempt !== false) {
        return $preempt;
    }
    $host = (string) wp_parse_url((string) $url, PHP_URL_HOST) ?: (string) $url;
    $test = 'outside a test';
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
        if (is_subclass_of($frame['class'] ?? '', \PHPUnit\Framework\TestCase::class)) {
            $test = $frame['class'] . '::' . $frame['function'];
        }
    }
    $GLOBALS['mollie_integration_blocked_requests'][$host][$test] =
        ($GLOBALS['mollie_integration_blocked_requests'][$host][$test] ?? 0) + 1;

    return new WP_Error('http_request_blocked', "Integration tests do not reach the network: {$host}.");
}, PHP_INT_MAX, 3);
register_shutdown_function(static function (): void {
    foreach ($GLOBALS['mollie_integration_blocked_requests'] as $host => $tests) {
        fwrite(STDERR, "Refused outgoing requests to {$host}, which no test answered:" . PHP_EOL);
        foreach ($tests as $test => $count) {
            fwrite(STDERR, "  {$count} in {$test}" . PHP_EOL);
        }
    }
});

// Ensure the TestCase class is loaded
require_once __DIR__ . '/IntegrationMockedTestCase.php';
