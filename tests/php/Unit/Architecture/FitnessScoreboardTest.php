<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Architecture;

use FilesystemIterator;
use Mollie\WooCommerceTests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * `composer fitness` counts the legacy patterns of RULES.md section 2 and holds them to a one-way
 * ratchet. The script is run as a black box: copied with the fixtures tree into a temporary
 * directory, so the root and the baseline it finds next to itself are the fixture's.
 *
 * @coversNothing
 */
class FitnessScoreboardTest extends TestCase
{
    private const SCRIPT = 'tests/architecture/fitness.php';

    private const BASELINE = 'tests/architecture/baseline.json';

    /**
     * Every metric of RULES.md section 2, in its order.
     */
    private const METRICS = [
        'handler-lookups',
        'container-params',
        'static-properties',
        'class-from-string',
        'method-classes-with-hooks',
        'orders-api-symbols',
        'data-importers',
        'superglobals-outside-entry',
        'mutating-sdk-calls',
        'api-key-readers',
        'raw-logging',
        'admin-ajax',
        'order-creation-paths',
        'library-mollie-symbols',
        'entry-points-without-admission-test',
        'new-meta-keys',
        'hook-bridges',
        'rules-without-check',
    ];

    private string $tree = '';

    protected function tearDown(): void
    {
        if ($this->tree !== '') {
            $this->remove($this->tree);
        }
        parent::tearDown();
    }

    /**
     * Scenario: the scoreboard passes on the branch as committed
     *   Given the repository as committed, with its baseline
     *   When `fitness.php` runs
     *   Then it exits 0
     *   And it prints one table in which every metric of RULES.md section 2 has exactly one row
     */
    public function testPrintsOneTableWithEveryMetricAndExitsZeroOnTheCommittedTree(): void
    {
        [$exit, $output] = $this->runScript(PROJECT_DIR);

        self::assertSame(0, $exit, "The scoreboard fails on the committed tree:\n" . $output);
        foreach (self::METRICS as $metric) {
            self::assertSame(
                1,
                $this->rowsNaming($output, $metric),
                sprintf("The table must have exactly one row for %s:\n%s", $metric, $output)
            );
        }
    }

    /**
     * Scenario: no new _mollie_* key is written on the branch as committed
     *   Given the repository as committed
     *   When `fitness.php --format=md` runs
     *   Then the new-meta-keys row shows 0 now
     */
    public function testCountsNoNewMetaKeysOnTheCommittedTree(): void
    {
        [, $output] = $this->runScript(PROJECT_DIR, ['--format=md']);

        self::assertSame('0', $this->nowOf($output, 'new-meta-keys'), $output);
    }

    /**
     * Scenario: a new lookup of the deprecated handlers fails the run
     *   Given a fixture tree whose baseline matches what it holds
     *   When a new file adds a lookup of __deprecated.gateway_helpers
     *   Then the run exits non-zero
     *   And a failure line starts with R-12 and the output names handler-lookups and the new file
     */
    public function testFailsNamingMetricRuleAndFileWhenANewFileLooksUpTheDeprecatedHandlers(): void
    {
        $this->fixtureTree();
        $this->runScript($this->tree, ['--update-baseline']);
        $this->write(
            'src/Fresh/Lookup.php',
            "<?php\nfunction fresh(\$container)\n{\n    return \$container->get('__deprecated.gateway_helpers');\n}\n"
        );

        [$exit, $output] = $this->runScript($this->tree);

        self::assertNotSame(0, $exit, "A new handler lookup must fail the run:\n" . $output);
        self::assertRegExp('/^R-12: /m', $output);
        self::assertStringContainsString('handler-lookups', $output);
        self::assertStringContainsString('src/Fresh/Lookup.php', $output);
    }

    /**
     * Scenario: numbers equal to the baseline pass
     *   Given a fixture tree whose baseline was just updated
     *   When the run is repeated with nothing changed
     *   Then it exits 0
     */
    public function testPassesWhenEveryRatchetNumberEqualsTheBaseline(): void
    {
        $this->fixtureTree();
        $this->runScript($this->tree, ['--update-baseline']);

        [$exit, $output] = $this->runScript($this->tree);

        self::assertSame(0, $exit, "An unchanged tree must pass:\n" . $output);
    }

    /**
     * Scenario: a better number fails until the baseline is updated
     *   Given a fixture tree whose baseline matches what it holds
     *   When its only handler lookup is removed
     *   Then the run exits non-zero and says to run --update-baseline
     *   And after --update-baseline only baseline.json has changed, the hand-typed numbers are kept,
     *       and the run exits 0
     */
    public function testFailsWithUpdateInstructionWhenAHitIsRemovedThenPassesAndOnlyTheBaselineChanged(): void
    {
        $this->fixtureTree();
        $this->runScript($this->tree, ['--update-baseline']);
        unlink($this->tree . '/src/Legacy/HandlerLookup.php');

        [$exit, $output] = $this->runScript($this->tree);

        self::assertNotSame(0, $exit, "A better number must fail until the baseline is updated:\n" . $output);
        self::assertStringContainsString('--update-baseline', $output);

        $before = $this->hashes($this->tree);
        $this->runScript($this->tree, ['--update-baseline']);
        $after = $this->hashes($this->tree);

        self::assertSame(array_keys($before), array_keys($after), 'Updating the baseline added or removed a file.');
        self::assertSame(
            [self::BASELINE],
            array_keys(array_diff_assoc($after, $before)),
            'Updating the baseline must change baseline.json and nothing else.'
        );
        $baseline = json_decode((string) file_get_contents($this->tree . '/' . self::BASELINE), true);
        self::assertSame(3, $baseline['entry-points-without-admission-test'] ?? null, 'A hand-typed number was lost.');
        self::assertSame(7, $baseline['rules-without-check'] ?? null, 'A hand-typed number was lost.');

        [$exit, $output] = $this->runScript($this->tree);
        self::assertSame(0, $exit, "After updating the baseline the run must pass:\n" . $output);
    }

    /**
     * Scenario: --list prints where every hit is, never what it says
     *   Given a fixture file that reads the API key on lines 9 and 14
     *   When `fitness.php --list=api-key-readers` runs
     *   Then it prints exactly those two path:line entries
     *   And it prints none of the file's content
     */
    public function testListsPathAndLineOfEveryApiKeyReaderWithoutFileContent(): void
    {
        $this->fixtureTree();

        [, $output] = $this->runScript($this->tree, ['--list=api-key-readers']);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
        self::assertSame(
            ['src/Legacy/ApiKeyReader.php:9', 'src/Legacy/ApiKeyReader.php:14'],
            $lines,
            "--list must print path:line of every hit and nothing else:\n" . $output
        );
        self::assertStringNotContainsString('getApiKey', $output, 'The script must never print file content.');
    }

    /**
     * Scenario: a new _mollie_* meta key fails the run naming R-25
     *   Given a fixture tree that writes only _mollie_process, so new-meta-keys is 0
     *   When a file in src/ adds update_meta_data('_mollie_foo', …)
     *   Then the run exits non-zero with a failure line starting with R-25
     */
    public function testFailsNamingR25WhenSrcWritesANewMollieMetaKey(): void
    {
        $this->fixtureTree();
        $this->runScript($this->tree, ['--update-baseline']);
        [, $table] = $this->runScript($this->tree, ['--format=md']);
        self::assertSame('0', $this->nowOf($table, 'new-meta-keys'), 'Writing _mollie_process must not count.');

        $this->write(
            'src/Fresh/MetaWriter.php',
            "<?php\nfunction remember(\$order)\n{\n    \$order->update_meta_data('_mollie_foo', 'yes');\n}\n"
        );
        [$exit, $output] = $this->runScript($this->tree);

        self::assertNotSame(0, $exit, "A new _mollie_* key must fail the run:\n" . $output);
        self::assertRegExp('/^R-25: /m', $output);
        self::assertStringContainsString('new-meta-keys', $output);
    }

    /**
     * Scenario: a new _mollie_* meta key written through a helper fails the run too
     *   Given a fixture tree where new-meta-keys is 0
     *   When a file in src/ reads a new key through a helper
     *   Then the row stays 0
     *   When it writes the key through a helper that takes the order first, as ExpressOrderWriter::setMeta() does
     *   Then the run exits non-zero with a failure line starting with R-25
     */
    public function testFailsNamingR25WhenSrcWritesANewMollieMetaKeyThroughAHelper(): void
    {
        $this->fixtureTree();
        $this->runScript($this->tree, ['--update-baseline']);

        $this->write(
            'src/Fresh/MetaReader.php',
            "<?php\nfunction recall(\$order)\n{\n    return \$this->getMeta(\$order, '_mollie_foo');\n}\n"
        );
        [, $table] = $this->runScript($this->tree, ['--format=md']);
        self::assertSame('0', $this->nowOf($table, 'new-meta-keys'), 'Reading a key must not count.');

        $this->write(
            'src/Fresh/MetaWriter.php',
            "<?php\nfunction remember(\$order)\n{\n    \$this->setMeta(\$order, '_mollie_foo', 'yes');\n}\n"
        );
        [$exit, $output] = $this->runScript($this->tree);

        self::assertNotSame(0, $exit, "A new _mollie_* key written through a helper must fail the run:\n" . $output);
        self::assertRegExp('/^R-25: /m', $output);
        self::assertStringContainsString('new-meta-keys', $output);
    }

    /**
     * Scenario: the express lookup keys are a recorded exception, not a blind spot
     *   Given ExpressOrderWriter writes the three _mollie_express_* keys through its helper
     *   When the scoreboard runs on the repository
     *   Then new-meta-keys is 0 because the script names those keys, and lists no other writer
     */
    public function testNamesTheExpressLookupKeysItAllows(): void
    {
        $script = (string) file_get_contents(PROJECT_DIR . '/' . self::SCRIPT);
        $writer = (string) file_get_contents(PROJECT_DIR . '/src/ExpressComponent/WooCommerce/ExpressOrderWriter.php');
        preg_match_all('/[\'"](_mollie_express_\w+)[\'"]/', $writer, $written);
        self::assertNotSame([], $written[1], 'The writer no longer names an express meta key.');

        foreach (array_unique($written[1]) as $key) {
            self::assertStringContainsString("'{$key}'", $script, "{$key} is written but not named in the scoreboard.");
        }
        [, $hits] = $this->runScript(PROJECT_DIR, ['--list=new-meta-keys']);
        self::assertSame('', trim($hits), 'A _mollie_* key outside the contract, the record and the express lookup keys is written.');
    }

    /**
     * Scenario: a blocking metric off its target fails even with an updated baseline
     *   Given a fixture library file that names the Mollie SDK
     *   When the baseline is updated and the run is repeated
     *   Then the run still exits non-zero, naming library-mollie-symbols
     */
    public function testFailsWhenABlockingMetricIsAboveItsTargetEvenWithAnUpdatedBaseline(): void
    {
        $this->fixtureTree();
        $this->write(
            'lib/payment-gateway/src/Leak.php',
            "<?php\nnamespace Fixture\\PaymentGateway;\n\nuse Mollie\\Api\\MollieApiClient;\n"
        );
        $this->runScript($this->tree, ['--update-baseline']);

        [$exit, $output] = $this->runScript($this->tree);

        self::assertNotSame(0, $exit, "A blocking metric above its target must fail:\n" . $output);
        self::assertStringContainsString('library-mollie-symbols', $output);
    }

    /**
     * Scenario: --format=md prints a table to paste into a PR description
     *   Given a fixture tree whose baseline matches what it holds
     *   When `fitness.php --format=md` runs
     *   Then every line is a Markdown table row: a header naming the six columns, a separator,
     *       and one row per metric
     */
    public function testPrintsAMarkdownTableWithFormatMd(): void
    {
        $this->fixtureTree();
        $this->runScript($this->tree, ['--update-baseline']);

        [$exit, $output] = $this->runScript($this->tree, ['--format=md']);

        self::assertSame(0, $exit, $output);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
        foreach ($lines as $line) {
            self::assertRegExp('/^\|.*\|$/', $line, "Not a Markdown table row: {$line}");
        }
        self::assertCount(2 + count(self::METRICS), $lines, $output);
        self::assertSame(
            ['metric', 'rule', 'baseline', 'now', 'target', 'mode'],
            array_map('strtolower', $this->cells($lines[0])),
            'The header must name the six columns.'
        );
        self::assertRegExp('/^\|(\s*:?-{3,}:?\s*\|){6}$/', $lines[1], 'The second line must be the separator.');
        self::assertSame(
            self::METRICS,
            array_map(function (string $row): string {
                return $this->cells($row)[0];
            }, array_slice($lines, 2)),
            'One row per metric, in the order of RULES.md section 2.'
        );
    }

    /**
     * Scenario: CI runs the scoreboard and fails the job when it fails
     *   Given composer.json and the CI workflow
     *   Then composer has a `fitness` script that runs tests/architecture/fitness.php
     *   And the workflow runs `composer fitness` in a step after "Run PHPUnit"
     *   And that step does not continue on error
     */
    public function testCiRunsComposerFitnessAfterPhpUnitAndFailsTheJob(): void
    {
        $composer = json_decode((string) file_get_contents(PROJECT_DIR . '/composer.json'), true);
        $script = $composer['scripts']['fitness'] ?? '';
        self::assertStringContainsString(self::SCRIPT, is_array($script) ? implode(' ', $script) : $script);

        $workflow = (string) file_get_contents(PROJECT_DIR . '/.github/workflows/ci.yml');
        $phpunit = strpos($workflow, 'name: Run PHPUnit');
        $fitness = strpos($workflow, 'run: composer fitness');
        self::assertNotFalse($phpunit, 'The PHPUnit step is gone.');
        self::assertNotFalse($fitness, 'CI does not run composer fitness.');
        self::assertGreaterThan($phpunit, $fitness, 'composer fitness must run after PHPUnit.');

        $stepStart = (int) strrpos(substr($workflow, 0, $fitness), '- name:');
        $nextStep = strpos($workflow, '- name:', $fitness);
        $step = substr($workflow, $stepStart, $nextStep === false ? null : $nextStep - $stepStart);
        self::assertStringNotContainsString('continue-on-error', $step, 'A failing scoreboard must fail the job.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Running the script
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Runs the script found under $root and returns its exit code and everything it printed.
     *
     * @param array<int, string> $arguments
     * @return array{0: int, 1: string}
     */
    private function runScript(string $root, array $arguments = []): array
    {
        $process = proc_open(
            array_merge([PHP_BINARY, $root . '/' . self::SCRIPT], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );
        self::assertIsResource($process, 'The script could not be started.');
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $output];
    }

    private function rowsNaming(string $output, string $metric): int
    {
        return preg_match_all('/^.*(?<![\w-])' . preg_quote($metric, '/') . '(?![\w-]).*$/m', $output);
    }

    /**
     * The "now" cell of a metric's row in the Markdown table.
     */
    private function nowOf(string $markdown, string $metric): string
    {
        foreach (explode("\n", $markdown) as $line) {
            $cells = $this->cells(trim($line));
            if (($cells[0] ?? '') === $metric) {
                return $cells[3] ?? '';
            }
        }
        self::fail("No row for {$metric}:\n{$markdown}");
    }

    /**
     * @return array<int, string>
     */
    private function cells(string $row): array
    {
        return array_map('trim', explode('|', trim($row, '|')));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The fixtures tree
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Copies fixtures/Fitness and the script into a fresh temporary root; *.php.inc become *.php.
     */
    private function fixtureTree(): void
    {
        self::assertFileExists(PROJECT_DIR . '/' . self::SCRIPT, 'The scoreboard script does not exist yet.');
        $this->tree = sys_get_temp_dir() . '/mollie-fitness-' . bin2hex(random_bytes(6));
        $source = __DIR__ . '/fixtures/Fitness';

        foreach ($this->filesIn($source) as $file) {
            $relative = substr($file, strlen($source) + 1);
            $this->write(preg_replace('/\.php\.inc$/', '.php', $relative), (string) file_get_contents($file));
        }
        $this->write(self::SCRIPT, (string) file_get_contents(PROJECT_DIR . '/' . self::SCRIPT));
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->tree . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * @return array<string, string> relative path => content hash
     */
    private function hashes(string $root): array
    {
        $hashes = [];
        foreach ($this->filesIn($root) as $file) {
            $hashes[substr($file, strlen($root) + 1)] = (string) md5_file($file);
        }
        ksort($hashes);

        return $hashes;
    }

    /**
     * @return array<int, string>
     */
    private function filesIn(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function remove(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}
