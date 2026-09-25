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
 * src/Core is pure, and this is what proves it (blueprint section 13, fitness function FF-02).
 *
 * A pure core is the load-bearing assumption of the whole target architecture: decisions can be
 * unit tested without WordPress, replayed from a log line, and reasoned about without asking what
 * the database or the clock said. Nothing stops that from eroding except a check that fails.
 * FF-02 is meant to be a PHPStan rule; until composer fitness exists, it is this test.
 *
 * The rules, verbatim from the blueprint's dependency table: no WordPress or WooCommerce function
 * or class, no Mollie\Api, no Psr\Container or Psr\Log, no superglobals, no time(), date() or
 * rand(), no static properties, no non-final classes.
 *
 * The detector below is what makes the check real, so the detector itself is pinned by fixtures:
 * one file per rule under fixtures/CoreImpurity, each breaking exactly one rule and nothing else.
 * A detector that silently stopped detecting would otherwise let src/Core rot while staying green.
 *
 * @covers \Mollie\WooCommerce\Core
 */
class CorePurityTest extends TestCase
{
    private const SUPERGLOBALS = [
        '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_COOKIE', '$_SESSION', '$_FILES', '$_ENV', '$GLOBALS',
    ];

    /**
     * Anything whose answer differs between two runs with the same input. Pass it in instead.
     */
    private const NON_DETERMINISTIC_FUNCTIONS = [
        'time', 'date', 'rand', 'mt_rand', 'random_int', 'random_bytes', 'microtime', 'uniqid',
        'hrtime', 'getdate', 'mktime', 'date_create', 'shuffle', 'array_rand',
    ];

    /**
     * Exact names that are unmistakably WordPress or WooCommerce.
     */
    private const PLATFORM_FUNCTIONS = [
        'get_option', 'update_option', 'delete_option', 'add_option',
        'add_action', 'add_filter', 'apply_filters', 'do_action', 'has_filter', 'remove_action',
        'get_transient', 'set_transient', 'delete_transient',
        'is_admin', 'current_time', 'current_user_can', 'get_current_user_id', 'wp_get_current_user',
        'admin_url', 'home_url', 'site_url', 'add_query_arg', 'absint',
        '__', '_e', '_x', '_n', 'esc_html__', 'esc_attr__',
        'get_post_meta', 'update_post_meta', 'delete_post_meta', 'get_post',
        'check_ajax_referer', 'WC',
    ];

    /**
     * Prefixes that only WordPress, WooCommerce or this plugin's platform layer use.
     */
    private const PLATFORM_PREFIXES = ['wp_', 'wc_', 'woocommerce_', 'is_wp_', 'esc_', 'sanitize_', 'mollie_wc_'];

    private const FORBIDDEN_NAMESPACES = ['Mollie\\Api', 'Psr\\Log', 'Psr\\Container'];

    /**
     * Scenario: the detector reports the rule a file breaks
     *   Given a fixture that breaks exactly one core purity rule
     *   When it is tokenised
     *   Then that rule, and only that rule, is reported
     *
     * @dataProvider impurityFixtures
     */
    public function testFlagsAFileThatBreaksACorePurityRule(string $fixture, string $expectedRule): void
    {
        $path = $this->fixturesDir() . '/' . $fixture;
        self::assertFileExists($path, 'The fixture that proves this rule is missing.');

        $violations = $this->violations((string) file_get_contents($path));

        self::assertContains(
            $expectedRule,
            array_column($violations, 'rule'),
            sprintf('The detector did not flag "%s" in %s.', $expectedRule, $fixture)
        );
        self::assertSame(
            [$expectedRule],
            array_values(array_unique(array_column($violations, 'rule'))),
            sprintf('%s is meant to break one rule only; it reported: %s', $fixture, $this->describe($violations))
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function impurityFixtures(): array
    {
        return [
            'calls a WordPress function' => ['wordpress-function.php.inc', 'wordpress'],
            'calls a WooCommerce function' => ['woocommerce-function.php.inc', 'wordpress'],
            'references Mollie\Api' => ['mollie-api.php.inc', 'mollie_api'],
            'references Psr\Log' => ['psr-log.php.inc', 'psr_log'],
            'references Psr\Container' => ['psr-container.php.inc', 'psr_container'],
            'reads a superglobal' => ['superglobal.php.inc', 'superglobal'],
            'calls time()' => ['time-call.php.inc', 'non_deterministic'],
            'calls date()' => ['date-call.php.inc', 'non_deterministic'],
            'calls rand()' => ['rand-call.php.inc', 'non_deterministic'],
            'declares a static property' => ['static-property.php.inc', 'static_property'],
            'declares a non-final class' => ['non-final-class.php.inc', 'non_final_class'],
        ];
    }

    /**
     * Scenario: every committed file under src/Core is pure
     *   Given the seed the core-seed spec commits
     *   When each file is tokenised
     *   Then no file breaks any rule
     *
     * Red until src/Core exists — a check with nothing to check is not a passing check.
     */
    public function testPassesForEveryCommittedFileUnderSrcCore(): void
    {
        $coreDir = PROJECT_DIR . '/src/Core';
        self::assertDirectoryExists($coreDir, 'src/Core does not exist yet.');

        $files = $this->phpFilesIn($coreDir);
        self::assertNotEmpty($files, 'src/Core holds no PHP files, so this test proves nothing.');

        $impure = [];
        foreach ($files as $file) {
            $violations = $this->violations((string) file_get_contents($file));
            if ($violations !== []) {
                $impure[] = $this->relative($file) . ': ' . $this->describe($violations);
            }
        }

        self::assertSame([], $impure, "src/Core is not pure:\n" . implode("\n", $impure));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The detector
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Every purity rule the given PHP source breaks.
     *
     * Note that the static rule catches a static local variable as well as a static property.
     * Both are shared mutable state (FF-09) and neither belongs in a pure function.
     *
     * @return array<int, array{rule: string, symbol: string}>
     */
    private function violations(string $code): array
    {
        $tokens = $this->meaningfulTokens($code);
        $violations = [];

        foreach ($tokens as $index => $token) {
            $violation = $this->violationAt($tokens, $index, $token);
            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @param array<int, array{0: int|string, 1: string}> $tokens
     * @param array{0: int|string, 1: string} $token
     * @return array{rule: string, symbol: string}|null
     */
    private function violationAt(array $tokens, int $index, array $token): ?array
    {
        [$id, $text] = $token;

        if ($id === T_VARIABLE && in_array($text, self::SUPERGLOBALS, true)) {
            return ['rule' => 'superglobal', 'symbol' => $text];
        }

        if ($this->isNameStart($tokens, $index)) {
            $name = $this->readName($tokens, $index);
            foreach (self::FORBIDDEN_NAMESPACES as $namespace) {
                if (strpos(ltrim($name, '\\'), $namespace . '\\') === 0) {
                    return ['rule' => $this->ruleForNamespace($namespace), 'symbol' => $name];
                }
            }
        }

        if ($id === T_STRING && $this->isFunctionCall($tokens, $index)) {
            if (in_array($text, self::NON_DETERMINISTIC_FUNCTIONS, true)) {
                return ['rule' => 'non_deterministic', 'symbol' => $text . '()'];
            }
            if ($this->isPlatformFunction($text)) {
                return ['rule' => 'wordpress', 'symbol' => $text . '()'];
            }
        }

        if ($id === T_STATIC && $this->declaresStaticState($tokens, $index)) {
            return ['rule' => 'static_property', 'symbol' => $this->nextVariable($tokens, $index)];
        }

        if ($id === T_CLASS && $this->isClassDeclaration($tokens, $index) && !$this->isFinal($tokens, $index)) {
            return ['rule' => 'non_final_class', 'symbol' => $tokens[$index + 1][1] ?? 'class'];
        }

        return null;
    }

    /**
     * Tokens with whitespace and comments removed, each normalised to [id, text].
     *
     * @return array<int, array{0: int|string, 1: string}>
     */
    private function meaningfulTokens(string $code): array
    {
        $meaningful = [];
        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $meaningful[] = [$token[0], $token[1]];
                continue;
            }
            $meaningful[] = [$token, $token];
        }

        return $meaningful;
    }

    /**
     * Whether a qualified name starts here and was not already consumed by the token before it.
     *
     * PHP 8 hands back a whole qualified name as one token; 7.4 hands back its pieces. Reading
     * the name by hand covers both.
     *
     * @param array<int, array{0: int|string, 1: string}> $tokens
     */
    private function isNameStart(array $tokens, int $index): bool
    {
        if (!$this->isNamePart($tokens[$index][0])) {
            return false;
        }

        return $index === 0 || !$this->isNamePart($tokens[$index - 1][0]);
    }

    /**
     * @param int|string $id
     */
    private function isNamePart($id): bool
    {
        $parts = [T_STRING, T_NS_SEPARATOR];
        foreach (['T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE'] as $constant) {
            if (defined($constant)) {
                $parts[] = constant($constant);
            }
        }

        return in_array($id, $parts, true);
    }

    /**
     * @param array<int, array{0: int|string, 1: string}> $tokens
     */
    private function readName(array $tokens, int $index): string
    {
        $name = '';
        for ($i = $index; isset($tokens[$i]) && $this->isNamePart($tokens[$i][0]); $i++) {
            $name .= $tokens[$i][1];
        }

        return $name;
    }

    private function ruleForNamespace(string $namespace): string
    {
        return [
            'Mollie\\Api' => 'mollie_api',
            'Psr\\Log' => 'psr_log',
            'Psr\\Container' => 'psr_container',
        ][$namespace];
    }

    /**
     * A bare function call: not a method, not a static call, not a declaration, not a class name.
     *
     * @param array<int, array{0: int|string, 1: string}> $tokens
     */
    private function isFunctionCall(array $tokens, int $index): bool
    {
        if (($tokens[$index + 1][1] ?? '') !== '(') {
            return false;
        }

        $before = $tokens[$index - 1][0] ?? null;
        $disqualifying = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_NS_SEPARATOR];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $disqualifying[] = T_NULLSAFE_OBJECT_OPERATOR;
        }

        return !in_array($before, $disqualifying, true);
    }

    private function isPlatformFunction(string $name): bool
    {
        if (in_array($name, self::PLATFORM_FUNCTIONS, true)) {
            return true;
        }
        foreach (self::PLATFORM_PREFIXES as $prefix) {
            if (strpos($name, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * `static` introducing state rather than a method, a closure or a `static::` reference.
     *
     * @param array<int, array{0: int|string, 1: string}> $tokens
     */
    private function declaresStaticState(array $tokens, int $index): bool
    {
        return $this->nextVariable($tokens, $index) !== '';
    }

    /**
     * The variable `static` introduces, or '' when it introduces something else.
     *
     * @param array<int, array{0: int|string, 1: string}> $tokens
     */
    private function nextVariable(array $tokens, int $index): string
    {
        $stoppers = [T_FUNCTION, T_DOUBLE_COLON];
        if (defined('T_FN')) {
            $stoppers[] = T_FN;
        }

        for ($i = $index + 1; isset($tokens[$i]); $i++) {
            [$id, $text] = $tokens[$i];
            if ($id === T_VARIABLE) {
                return $text;
            }
            if (in_array($id, $stoppers, true) || in_array($text, ['(', '{', ';'], true)) {
                return '';
            }
        }

        return '';
    }

    /**
     * @param array<int, array{0: int|string, 1: string}> $tokens
     */
    private function isClassDeclaration(array $tokens, int $index): bool
    {
        $before = $tokens[$index - 1][0] ?? null;
        if (in_array($before, [T_NEW, T_DOUBLE_COLON], true)) {
            return false;
        }

        return ($tokens[$index + 1][0] ?? null) === T_STRING;
    }

    /**
     * @param array<int, array{0: int|string, 1: string}> $tokens
     */
    private function isFinal(array $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $id = $tokens[$i][0];
            if ($id === T_FINAL) {
                return true;
            }
            if ($id !== T_ABSTRACT) {
                return false;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Files
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function fixturesDir(): string
    {
        return __DIR__ . '/fixtures/CoreImpurity';
    }

    private function relative(string $path): string
    {
        return str_replace(PROJECT_DIR . '/', '', $path);
    }

    /**
     * @param array<int, array{rule: string, symbol: string}> $violations
     */
    private function describe(array $violations): string
    {
        return implode(', ', array_map(static function (array $violation): string {
            return $violation['rule'] . ' (' . $violation['symbol'] . ')';
        }, $violations));
    }
}
