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
 * R-02: every class under a Rules/ folder whose name ends in Decision or Guards has a unit test
 * that declares a data provider. src/<Module>/Rules/FooDecision.php is tested by
 * tests/php/Unit/<Module>/Rules/FooDecisionTest.php. The detector is shown failing against
 * temporary trees.
 *
 * @coversNothing
 */
class RulesHaveTableTestsTest extends TestCase
{
    private string $tree = '';

    protected function tearDown(): void
    {
        if ($this->tree !== '') {
            $this->remove($this->tree);
        }
        parent::tearDown();
    }

    /**
     * Scenario: every decision and guard in the plugin has a table test
     *   Given every *Decision and *Guards class under a Rules/ folder in src/
     *   When its test file under tests/php/Unit is looked up
     *   Then it exists and declares a data provider
     *
     * A check with nothing to check is not a passing check, so finding no class fails.
     */
    public function testEveryDecisionAndGuardsUnderRulesHasATableTest(): void
    {
        $src = PROJECT_DIR . '/src';
        self::assertNotEmpty($this->rulesClassesIn($src), 'No *Decision or *Guards class found, so this test proves nothing.');

        $untested = $this->untested($src, PROJECT_DIR . '/tests/php/Unit');

        self::assertSame(
            [],
            $untested,
            "R-02: give each of these a unit test with a data provider, one row per case:\n  "
            . implode("\n  ", $untested)
        );
    }

    /**
     * Scenario: a decision without a table test is reported
     *   Given a Rules/ class ending in Decision or Guards
     *   And no test file for it, or a test file without a data provider
     *   When the detector runs
     *   Then it reports that class
     *
     * @dataProvider untestedRules
     * @param array<string, string> $sources
     * @param array<string, string> $tests
     */
    public function testFlagsARulesClassWithoutATableTest(array $sources, array $tests, string $expected): void
    {
        $this->tree($sources, $tests);

        self::assertSame([$expected], $this->untested($this->tree . '/src', $this->tree . '/tests'));
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: array<string, string>, 2: string}>
     */
    public function untestedRules(): array
    {
        $decision = ['Mod/Rules/FooDecision.php' => $this->classSource('FooDecision')];
        $guards = ['Mod/Rules/WebhookGuards.php' => $this->classSource('WebhookGuards')];
        $plainTest = $this->testSource('public function testDecides(): void {}');

        return [
            'Decision with no test file' => [$decision, [], 'Mod/Rules/FooDecision.php'],
            'Decision whose test has no data provider' => [
                $decision,
                ['Mod/Rules/FooDecisionTest.php' => $plainTest],
                'Mod/Rules/FooDecision.php',
            ],
            'Guards with no test file' => [$guards, [], 'Mod/Rules/WebhookGuards.php'],
            'Guards whose test has no data provider' => [
                $guards,
                ['Mod/Rules/WebhookGuardsTest.php' => $plainTest],
                'Mod/Rules/WebhookGuards.php',
            ],
        ];
    }

    /**
     * Scenario: a table-tested decision and other classes are not reported
     *   Given a *Decision under Rules/ whose test declares a data provider
     *   And a value under Rules/ and a *Decision outside any Rules/ folder, both untested
     *   When the detector runs
     *   Then nothing is reported
     */
    public function testAcceptsATableTestAndIgnoresOtherClasses(): void
    {
        $this->tree(
            [
                'Mod/Rules/FooDecision.php' => $this->classSource('FooDecision'),
                'Mod/Rules/Money.php' => $this->classSource('Money'),
                'Mod/Handler/BarDecision.php' => $this->classSource('BarDecision'),
            ],
            [
                'Mod/Rules/FooDecisionTest.php' => $this->testSource(
                    "/** @dataProvider cases */\npublic function testDecides(): void {}"
                ),
            ]
        );

        self::assertSame([], $this->untested($this->tree . '/src', $this->tree . '/tests'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The detector
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Each *Decision or *Guards class under a Rules/ folder whose test is missing or has no data
     * provider, relative to $src.
     *
     * @return array<int, string>
     */
    private function untested(string $src, string $tests): array
    {
        $untested = [];
        foreach ($this->rulesClassesIn($src) as $relative) {
            $test = $tests . '/' . preg_replace('/\.php$/', 'Test.php', $relative);
            if (!is_file($test) || strpos((string) file_get_contents($test), '@dataProvider') === false) {
                $untested[] = $relative;
            }
        }

        return $untested;
    }

    /**
     * @return array<int, string> paths relative to $src
     */
    private function rulesClassesIn(string $src): array
    {
        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($src) + 1);
            if (preg_match('#(^|/)Rules/(.+/)?[^/]+(Decision|Guards)\.php$#', $relative) === 1) {
                $classes[] = $relative;
            }
        }
        sort($classes);

        return $classes;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Temporary trees
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $sources relative to src/
     * @param array<string, string> $tests relative to tests/
     */
    private function tree(array $sources, array $tests): void
    {
        $this->tree = sys_get_temp_dir() . '/mollie-rules-' . bin2hex(random_bytes(6));
        $files = [];
        foreach ($sources as $path => $content) {
            $files['src/' . $path] = $content;
        }
        foreach ($tests as $path => $content) {
            $files['tests/' . $path] = $content;
        }
        foreach ($files as $path => $content) {
            $full = $this->tree . '/' . $path;
            if (!is_dir(dirname($full))) {
                mkdir(dirname($full), 0777, true);
            }
            file_put_contents($full, $content);
        }
        if (!is_dir($this->tree . '/tests')) {
            mkdir($this->tree . '/tests', 0777, true);
        }
    }

    private function classSource(string $class): string
    {
        return "<?php\nfinal class {$class}\n{\n}\n";
    }

    private function testSource(string $body): string
    {
        return "<?php\nclass FixtureTest\n{\n{$body}\n}\n";
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
