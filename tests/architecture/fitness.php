<?php

/**
 * The architecture scoreboard: `composer fitness`.
 *
 * Counts the legacy patterns of docs/architecture/RULES.md section 2 with regular expressions over
 * src/ (and lib/payment-gateway/src for library-mollie-symbols), compares each number with
 * baseline.json next to this file, and prints one table. A ratchet number may only go down, and
 * going down needs the baseline updated in the same PR; a blocking number must be on its target.
 *
 * Options: --update-baseline, --list=<metric> (path:line of every hit), --format=md.
 * The script reads files and prints paths and line numbers, never file content.
 */

declare(strict_types=1);

namespace Mollie\WooCommerce\Fitness;

const RATCHET = 'ratchet';
const BLOCKING = 'blocking';
const MANUAL = 'manual';

/**
 * The three classes the library's keys resolve back into (cut C3).
 */
const DEPRECATED_HANDLERS = [
    'MolliePaymentGatewayHandler',
    'MollieSubscriptionGatewayHandler',
    'MollieSepaRecurringGatewayHandler',
];

/**
 * The _mollie_* keys legacy code writes today; they are public contract and keep their writers.
 * Measured on dev/PIWOO-944-refactor-01 on 2026-10-01. A key that is not here is a new one.
 */
const COMPATIBLE_META_KEYS = [
    '_mollie_authorized',
    '_mollie_cancelled_payment_id',
    '_mollie_customer_id',
    '_mollie_mandate_id',
    '_mollie_open_status_note',
    '_mollie_order_id',
    '_mollie_paid_and_processed',
    '_mollie_paid_by_other_gateway',
    '_mollie_payment_id',
    '_mollie_payment_instructions',
    '_mollie_payment_method_button',
    '_mollie_payment_mode',
    '_mollie_processed_chargeback_ids',
    '_mollie_processed_refund_ids',
];

/**
 * The keys an express order is found by: its reference, its session and that session's expiry. They
 * are queried (meta_key, meta_query), which a field of the process record cannot be. Recorded as an
 * exception in RULES.md section 3.
 */
const EXPRESS_LOOKUP_META_KEYS = [
    '_mollie_express_expires_at',
    '_mollie_express_ref',
    '_mollie_express_session_id',
];

/**
 * Every metric of RULES.md section 2, in its order.
 *
 * scope: where the files are. count: 'hits' counts lines, 'files' counts files with a hit.
 * match: path relative to the root, file content => line numbers of the hits.
 * instead: the sentence a failure line gives after the rule id.
 *
 * @return array<string, array{rule: string, target: ?int, mode: string, scope: string, count: string, match: ?callable, instead: string}>
 */
function metrics(): array
{
    return [
        'handler-lookups' => [
            'rule' => 'R-12',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                $lines = linesOf($code, "/'__deprecated\.gateway_helpers'(?!\s*=>)/");
                if (!in_array(basename($path, '.php'), DEPRECATED_HANDLERS, true)) {
                    $naming = linesOf($code, '/\b(' . implode('|', DEPRECATED_HANDLERS) . ')\b/');
                    if ($naming !== []) {
                        $lines[] = $naming[0];
                    }
                }

                return uniqueSorted($lines);
            },
            'instead' => 'get what you need from a module service, not from __deprecated.gateway_helpers or a deprecated handler class.',
        ],
        'container-params' => [
            'rule' => 'R-12',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'files',
            'match' => static function (string $path, string $code): array {
                if (preg_match('#(Module\.php$|/inc/)#', $path) === 1) {
                    return [];
                }

                return linesOf($code, '/ContainerInterface\s+\$\w+/');
            },
            'instead' => 'pass the services a class needs to its constructor; only a module sees the container.',
        ],
        'static-properties' => [
            'rule' => 'R-15',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'files',
            'match' => static function (string $path, string $code): array {
                return linesOf(
                    $code,
                    '/^\s*(?:(?:public|protected|private|var)\s+static|static\s+(?:public|protected|private))\s+(?:\??[\w\\\\|]+\s+)?\$\w+/m'
                );
            },
            'instead' => 'keep the state in an instance property of a service.',
        ],
        'class-from-string' => [
            'rule' => 'R-15',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                return linesOf($code, '/\bclass_exists\(\s*\$|\bnew\s+\$/');
            },
            'instead' => 'build the object in services.php or choose it from a map of closures.',
        ],
        'method-classes-with-hooks' => [
            'rule' => 'R-11',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'files',
            'match' => static function (string $path, string $code): array {
                if (preg_match('/\bextends\s+AbstractPaymentMethod\b/', $code) !== 1) {
                    return [];
                }

                return linesOf($code, '/\b(?:add_action|add_filter)\s*\(|ContainerInterface|\$container\b|->container\b/');
            },
            'instead' => 'a payment-method class only declares configuration; register hooks and use services in its module.',
        ],
        'orders-api-symbols' => [
            'rule' => 'R-13',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'files',
            'match' => static function (string $path, string $code): array {
                return linesOf(
                    $code,
                    '/Resources\\\\Order\b|->orders->|\bord_|_mollie_order_id|isOrderApiSetting|\bMollieOrder\b|\bOrderLines\b|context\s*===\s*[\'"]order[\'"]/'
                );
            },
            'instead' => 'use the Payments API; the Orders API only shrinks (cut C2).',
        ],
        'data-importers' => [
            'rule' => 'R-14',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'files',
            'match' => static function (string $path, string $code): array {
                return linesOf($code, '/^use\s+Mollie\\\\WooCommerce\\\\Shared\\\\Data;|->get\(\s*[\'"]settings\.data_helper[\'"]/m');
            },
            'instead' => 'replace the Data methods you need with a value or a function in your own module (cut C5).',
        ],
        'superglobals-outside-entry' => [
            'rule' => '',
            'target' => null,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'files',
            'match' => static function (string $path, string $code): array {
                if (strpos($path, '/Entry/') !== false) {
                    return [];
                }

                return linesOf($code, '/\$_(?:POST|GET|REQUEST|SERVER)\b/');
            },
            'instead' => 'read the request in an Entry/ class and pass plain values on.',
        ],
        'mutating-sdk-calls' => [
            'rule' => 'R-06',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                if (strpos($path, 'src/SDK/') === 0) {
                    return [];
                }
                $endpoint = '->(?:payments|orders|refunds|paymentRefunds|orderRefunds|paymentCaptures|customers'
                    . '|customerPayments|mandates|subscriptions|shipments|paymentLinks|sessions)'
                    . '->(?:create|refund|cancel|capture|update|delete)\w*\s*\(';
                $resource = '->(?:createRefund|cancelLines|cancelAllLines|createShipment|shipAll|createCapture)\s*\(';

                return linesOf($code, '/' . $endpoint . '|' . $resource . '/');
            },
            'instead' => 'create, refund, capture, cancel and charge through SDK\MollieApi with an IdempotencyKey.',
        ],
        'api-key-readers' => [
            'rule' => 'R-07',
            'target' => 1,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'files',
            'match' => static function (string $path, string $code): array {
                return linesOf($code, '/\bgetApiKey\s*\(|[(,]\s*\K\$apiKey(?=\s*[,)])/');
            },
            'instead' => 'let SDK\SdkMollieApi read the key and call Mollie through it.',
        ],
        'raw-logging' => [
            'rule' => 'R-08',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                if (strpos($path, 'src/Log/') === 0) {
                    return [];
                }
                $direct = '(?:logger|wc_get_logger\(\))->(?:log|debug|info|notice|warning|error|critical|alert|emergency)\s*\(';
                $encoded = '(?:print_r|var_export|json_encode|serialize)\s*\(';

                return uniqueSorted(array_merge(
                    linesOf($code, '/' . $direct . '/'),
                    array_values(array_intersect(linesOf($code, '/' . $encoded . '/'), linesOf($code, '/log/i')))
                ));
            },
            'instead' => 'log through Log\EventLog with an event and fields listed in events.md.',
        ],
        'admin-ajax' => [
            'rule' => 'R-16',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                return linesOf($code, '/[\'"]wp_ajax_/');
            },
            'instead' => 'register a REST route with a permission callback instead of an admin-ajax action.',
        ],
        'order-creation-paths' => [
            'rule' => '',
            'target' => 2,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                return linesOf($code, '/\bwc_create_order\s*\(|->create_order\s*\(/');
            },
            'instead' => 'create the order through WC()->checkout()->create_order() or the Store API checkout (cut C4).',
        ],
        'library-mollie-symbols' => [
            'rule' => '',
            'target' => 0,
            'mode' => BLOCKING,
            'scope' => 'lib',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                return linesOf($code, '/\bMollie\b/');
            },
            'instead' => 'keep lib/payment-gateway generic; Mollie code belongs in src/.',
        ],
        'entry-points-without-admission-test' => [
            'rule' => 'R-20',
            'target' => 0,
            'mode' => MANUAL,
            'scope' => '',
            'count' => 'hits',
            'match' => null,
            'instead' => 'give every route, AJAX action and admin action a test with an unauthenticated case.',
        ],
        'new-meta-keys' => [
            'rule' => 'R-25',
            'target' => 0,
            'mode' => RATCHET,
            'scope' => 'src',
            'count' => 'hits',
            'match' => static function (string $path, string $code): array {
                $lines = [];
                // Any call named after meta, with the key first or after the order: a helper counts too.
                $found = preg_match_all(
                    '/\b(\w*meta\w*)\(\s*(?:\$[\w>-]+\s*,\s*)?[\'"](_mollie_\w+)[\'"]/i',
                    $code,
                    $matches,
                    PREG_OFFSET_CAPTURE
                );
                $known = array_merge(['_mollie_process'], COMPATIBLE_META_KEYS, EXPRESS_LOOKUP_META_KEYS);
                for ($i = 0; $i < (int) $found; $i++) {
                    $isRead = preg_match('/^(?:get|has|delete)|exists$/i', $matches[1][$i][0]) === 1;
                    if (!$isRead && !in_array($matches[2][$i][0], $known, true)) {
                        $lines[] = lineAt($code, $matches[2][$i][1]);
                    }
                }

                return uniqueSorted($lines);
            },
            'instead' => 'store what only we know in the process record _mollie_process, not in a new _mollie_* key.',
        ],
        'hook-bridges' => [
            'rule' => 'R-27',
            'target' => 4,
            'mode' => MANUAL,
            'scope' => '',
            'count' => 'hits',
            'match' => null,
            'instead' => 'add the filter immediately before the WooCommerce call, remove it in finally, and list it.',
        ],
        'rules-without-check' => [
            'rule' => '',
            'target' => null,
            'mode' => MANUAL,
            'scope' => '',
            'count' => 'hits',
            'match' => null,
            'instead' => 'give the rule a check.',
        ],
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// Measuring
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Every hit of one metric as [path, line], paths relative to the root.
 *
 * @return array<int, array{0: string, 1: int}>
 */
function hits(string $root, array $metric): array
{
    if ($metric['match'] === null) {
        return [];
    }

    $hits = [];
    foreach (phpFiles($root, scopeDirectory($metric['scope'])) as $path) {
        foreach (($metric['match'])($path, (string) file_get_contents($root . '/' . $path)) as $line) {
            $hits[] = [$path, $line];
        }
    }

    return $hits;
}

/**
 * The number a metric shows: hits or files with a hit, or the hand-typed baseline value.
 *
 * @param array<string, int> $baseline
 */
function measure(string $root, string $name, array $metric, array $baseline): int
{
    if ($metric['mode'] === MANUAL) {
        return $baseline[$name] ?? 0;
    }

    $hits = hits($root, $metric);
    if ($metric['count'] === 'files') {
        return count(array_unique(array_column($hits, 0)));
    }

    return count($hits);
}

function scopeDirectory(string $scope): string
{
    return $scope === 'lib' ? 'lib/payment-gateway/src' : 'src';
}

/**
 * @return array<int, string> paths relative to the root, sorted
 */
function phpFiles(string $root, string $directory): array
{
    if (!is_dir($root . '/' . $directory)) {
        return [];
    }

    $files = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS)
    );

    /** @var \SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
        }
    }
    sort($files);

    return $files;
}

/**
 * The line numbers where the pattern matches, once per line. A match may span lines; it counts
 * on the line it starts.
 *
 * @return array<int, int>
 */
function linesOf(string $code, string $pattern): array
{
    $lines = [];
    $found = preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
    for ($i = 0; $i < (int) $found; $i++) {
        $lines[] = lineAt($code, $matches[0][$i][1]);
    }

    return uniqueSorted($lines);
}

function lineAt(string $code, int $offset): int
{
    return substr_count($code, "\n", 0, $offset) + 1;
}

/**
 * @param array<int, int> $lines
 * @return array<int, int>
 */
function uniqueSorted(array $lines): array
{
    $lines = array_values(array_unique($lines));
    sort($lines);

    return $lines;
}

// ──────────────────────────────────────────────────────────────────────────────
// Judging
// ──────────────────────────────────────────────────────────────────────────────

/**
 * The failure lines for one metric; empty when it passes.
 *
 * @return array<int, string>
 */
function failures(string $root, string $name, array $metric, ?int $baseline, int $now): array
{
    $rule = $metric['rule'] !== '' ? $metric['rule'] : 'R-24';

    if ($metric['mode'] === MANUAL) {
        return [];
    }

    if ($metric['mode'] === BLOCKING) {
        if ($now <= (int) $metric['target']) {
            return [];
        }

        return array_merge(
            [sprintf('%s: %s is %d, its target is %d: %s', $rule, $name, $now, $metric['target'], $metric['instead'])],
            hitLines($root, $metric)
        );
    }

    if ($baseline === null) {
        return [sprintf(
            '%s: %s has no baseline: run `composer fitness -- --update-baseline` and commit tests/architecture/baseline.json.',
            $rule,
            $name
        )];
    }

    if ($now > $baseline) {
        return array_merge(
            [sprintf('%s: %s went from %d to %d: %s', $rule, $name, $baseline, $now, $metric['instead'])],
            hitLines($root, $metric)
        );
    }

    if ($now < $baseline) {
        return [sprintf(
            '%s: %s went from %d to %d, better than the baseline: run `composer fitness -- --update-baseline` and commit tests/architecture/baseline.json.',
            $rule,
            $name,
            $baseline,
            $now
        )];
    }

    return [];
}

/**
 * @return array<int, string>
 */
function hitLines(string $root, array $metric): array
{
    return array_map(static function (array $hit): string {
        return '    ' . $hit[0] . ':' . $hit[1];
    }, hits($root, $metric));
}

// ──────────────────────────────────────────────────────────────────────────────
// Printing
// ──────────────────────────────────────────────────────────────────────────────

/**
 * @param array<int, array<int, string>> $rows
 */
function table(array $rows, string $format): string
{
    $header = ['metric', 'rule', 'baseline', 'now', 'target', 'mode'];

    if ($format === 'md') {
        $lines = ['| ' . implode(' | ', $header) . ' |', '|' . str_repeat('---|', count($header))];
        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', $row) . ' |';
        }

        return implode("\n", $lines) . "\n";
    }

    $widths = array_map('strlen', $header);
    foreach ($rows as $row) {
        foreach ($row as $i => $cell) {
            $widths[$i] = max($widths[$i], strlen($cell));
        }
    }
    $lines = [];
    foreach (array_merge([$header], $rows) as $row) {
        $cells = [];
        foreach ($row as $i => $cell) {
            $cells[] = str_pad($cell, $widths[$i]);
        }
        $lines[] = rtrim(implode('  ', $cells));
    }

    return implode("\n", $lines) . "\n";
}

// ──────────────────────────────────────────────────────────────────────────────
// Baseline
// ──────────────────────────────────────────────────────────────────────────────

/**
 * @return array<string, int>
 */
function readBaseline(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $baseline = json_decode((string) file_get_contents($file), true);

    return is_array($baseline) ? array_map('intval', $baseline) : [];
}

/**
 * Computed numbers are measured again; hand-typed ones are kept.
 *
 * @param array<string, int> $baseline
 */
function writeBaseline(string $root, string $file, array $baseline): void
{
    $updated = [];
    foreach (metrics() as $name => $metric) {
        $updated[$name] = measure($root, $name, $metric, $baseline);
    }
    file_put_contents($file, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

// ──────────────────────────────────────────────────────────────────────────────
// Main
// ──────────────────────────────────────────────────────────────────────────────

/**
 * @param array<int, string> $arguments
 */
function main(array $arguments): int
{
    $root = dirname(__DIR__, 2);
    $baselineFile = __DIR__ . '/baseline.json';
    $metrics = metrics();

    $format = 'text';
    $list = null;
    $update = false;
    foreach ($arguments as $argument) {
        if ($argument === '--update-baseline') {
            $update = true;
        } elseif (strpos($argument, '--list=') === 0) {
            $list = substr($argument, strlen('--list='));
        } elseif ($argument === '--format=md') {
            $format = 'md';
        } else {
            fwrite(STDERR, "Unknown option {$argument}. Use --update-baseline, --list=<metric> or --format=md.\n");

            return 2;
        }
    }

    if ($list !== null) {
        if (!isset($metrics[$list])) {
            fwrite(STDERR, "Unknown metric {$list}. Known: " . implode(', ', array_keys($metrics)) . "\n");

            return 2;
        }
        foreach (hits($root, $metrics[$list]) as [$path, $line]) {
            echo $path . ':' . $line . "\n";
        }

        return 0;
    }

    if ($update) {
        writeBaseline($root, $baselineFile, readBaseline($baselineFile));
    }

    $baseline = readBaseline($baselineFile);
    $rows = [];
    $failures = [];
    foreach ($metrics as $name => $metric) {
        $now = measure($root, $name, $metric, $baseline);
        $rows[] = [
            $name,
            $metric['rule'] !== '' ? $metric['rule'] : '-',
            isset($baseline[$name]) ? (string) $baseline[$name] : '-',
            (string) $now,
            $metric['target'] !== null ? (string) $metric['target'] : 'down',
            $metric['mode'],
        ];
        array_push($failures, ...failures($root, $name, $metric, $baseline[$name] ?? null, $now));
    }

    echo table($rows, $format);
    if ($failures !== []) {
        fwrite(STDERR, "\n" . implode("\n", $failures) . "\n");

        return 1;
    }

    return 0;
}

exit(main(array_slice($argv, 1)));
