<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Architecture;

use FilesystemIterator;
use Mollie\WooCommerceTests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Within the new code, only SDK\SdkMollieApi reads the API key; any other reader fails with its
 * file name. Legacy code is out of scope.
 *
 * @covers \Mollie\WooCommerce\SDK\SdkMollieApi
 */
class ApiKeyChokepointTest extends TestCase
{
    private const CHOKEPOINT = 'src/SDK/SdkMollieApi.php';

    /**
     * The two spellings that read the key: the accessor, and the option ids it reads.
     */
    private const KEY_PATTERNS = ['getApiKey(', 'api_key'];

    /**
     * Everything the new code adds, directories and single files. Legacy code is deliberately out of
     * scope: src/SDK/Api.php and the settings still read the key today.
     */
    private const NEW_CODE = [
        'src/ExpressComponent',
        'src/Shared/Values',
        'src/Payment/Rules',
        'src/Components/Rules',
        'src/SDK/MollieApi.php',
        'src/SDK/SdkMollieApi.php',
        'src/SDK/MollieCallFailed.php',
        'src/SDK/IdempotencyKey.php',
        'src/Log/EventLog.php',
        'src/Payment/OrderLock.php',
        'src/Payment/OrderLockTimeout.php',
        'src/Shared/Clock.php',
        'src/Shared/SystemClock.php',
    ];

    /**
     * Scenario: only the Mollie adapter reads the API key
     *   Given every file of the new code
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
        foreach (self::NEW_CODE as $entry) {
            $path = PROJECT_DIR . '/' . $entry;
            self::assertFileExists($path, "{$entry} is listed as new code but does not exist.");
            foreach (is_dir($path) ? $this->phpFilesIn($path) : [$path] as $file) {
                $matched = $this->patternsIn((string) file_get_contents($file));
                if ($matched !== []) {
                    $readers[$this->relative($file)] = $matched;
                }
            }
        }

        self::assertSame(
            [self::CHOKEPOINT],
            array_keys($readers),
            "The API key is read outside SDK\\SdkMollieApi:\n" . $this->describe($readers)
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
