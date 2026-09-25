<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Architecture;

use FilesystemIterator;
use Mollie\WooCommerceTests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * One class reads the API key, and this is the check that keeps it at one (ADR-013, finding S-08).
 *
 * The key passes through 19 files today. That is the finding the seed exists to stop spreading:
 * every reader is a place it can be logged, echoed into a template, handed to a filter or sent to
 * the browser, and every one of them has to be reviewed again whenever key handling changes. The
 * blueprint's answer is a chokepoint — Adapter\Mollie\SdkMollieApi resolves the key internally and
 * nothing else ever sees it.
 *
 * This test does not clean up the 19. It draws the line around the new code, so the count can only
 * go down: the moment a second file under src/Core, src/Workflow, src/Adapter or src/ExpressComponent
 * reaches for the key, the suite says so, with the file name.
 *
 * @covers \Mollie\WooCommerce\Adapter\Mollie\SdkMollieApi
 */
class ApiKeyChokepointTest extends TestCase
{
    private const CHOKEPOINT = 'src/Adapter/Mollie/SdkMollieApi.php';

    /**
     * The two spellings the criterion names: the accessor, and the option ids it reads.
     */
    private const KEY_PATTERNS = ['getApiKey(', 'api_key'];

    /**
     * Everything the seed adds. Legacy directories are deliberately out of scope.
     */
    private const NEW_CODE_DIRS = ['src/Core', 'src/Workflow', 'src/Adapter', 'src/ExpressComponent'];

    /**
     * Scenario: only the Mollie adapter reads the API key
     *   Given every file the seed commits under src/Core, src/Workflow, src/Adapter and src/ExpressComponent
     *   When each is searched for getApiKey( and api_key
     *   Then the only file that matches is the SdkMollieApi adapter
     */
    public function testOnlyTheMollieAdapterReadsTheApiKey(): void
    {
        self::assertFileExists(
            PROJECT_DIR . '/' . self::CHOKEPOINT,
            'The chokepoint itself does not exist yet, so nothing is protecting the key.'
        );

        $readers = [];
        foreach (self::NEW_CODE_DIRS as $dir) {
            $path = PROJECT_DIR . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            foreach ($this->phpFilesIn($path) as $file) {
                $matched = $this->patternsIn((string) file_get_contents($file));
                if ($matched !== []) {
                    $readers[$this->relative($file)] = $matched;
                }
            }
        }

        self::assertSame(
            [self::CHOKEPOINT],
            array_keys($readers),
            "The API key is read outside Adapter\\Mollie\\SdkMollieApi:\n" . $this->describe($readers)
        );
        self::assertNotSame(
            [],
            $readers[self::CHOKEPOINT] ?? [],
            'SdkMollieApi must be the class that resolves the key, not a pass-through.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function patternsIn(string $code): array
    {
        return array_values(array_filter(self::KEY_PATTERNS, static function (string $pattern) use ($code): bool {
            return strpos($code, $pattern) !== false;
        }));
    }

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

    private function relative(string $path): string
    {
        return str_replace(PROJECT_DIR . '/', '', $path);
    }

    /**
     * @param array<string, array<int, string>> $readers
     */
    private function describe(array $readers): string
    {
        $lines = [];
        foreach ($readers as $file => $patterns) {
            $lines[] = '  ' . $file . ' — matches: ' . implode(', ', $patterns);
        }

        return implode("\n", $lines);
    }
}
