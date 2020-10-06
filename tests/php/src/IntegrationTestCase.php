<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Src;

use Closure;
use Exception;
use Inpsyde\Dbal\Dbal;
use Inpsyde\Fasti\Application\Core\Bootstrap;
use Inpsyde\Fasti\Application\Core\ContainerReadOnlySubset;
use Inpsyde\Fasti\Application\Core\ServiceProvider;
use Mollie\WooCommerceTests\TestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

/**
 * Base test class for Integration tests
 */
class IntegrationTestCase extends TestCase
{
    /**
     * @var ContainerReadOnlySubset|null
     */
    protected $containerSubset;

    /**
     * @var bool
     */
    private static $initialized = false;

    /**
     * @return ServiceProvider[]
     */
    protected function providers(): array
    {
        return [];
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ABSPATH') || self::$initialized) {
            return;
        }

        self::initializeWp();

        require_once ABSPATH . 'wp-includes/plugin.php';

        add_filter(
            Bootstrap::FILTER_SERVICE_PROVIDERS,
            function (): array {
                return $this->providers();
            }
        );

        @\unlink(ABSPATH . '/wp-content/plugins/app');
        \symlink(getenv('LIB_DIR'), ABSPATH . '/wp-content/plugins/app');

        require_once rtrim(getenv('LIB_DIR'), '/') . '/index.php';

        require_once ABSPATH . 'wp-config.php';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-config.php')) {
            throw new Exception('Cannot reset DB: WP is not initialized.');
        }

        self::resetDbal();
        self::runWpCliCommand(['db', 'drop', '--yes']);
        @unlink(ABSPATH . 'wp-config.php');

        self::$initialized = false;
        $this->containerSubset = null;

        parent::tearDown();
    }

    /**
     * @param ContainerReadOnlySubset $container
     * @return void
     */
    public function useContainerSubset(ContainerReadOnlySubset $container): void
    {
        $this->containerSubset = $container;
    }

    /**
     * @return void
     */
    protected function mockServer(
        string $url = 'https://example.com/',
        string $method = 'GET'
    ): void {

        $parts = parse_url($url);

        $_SERVER['SERVER_NAME'] = $parts['host'];
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_HOST'] = $parts['host'];
        $_SERVER['HTTPS'] = $parts['scheme'] === 'https' ? 'on' : '';
        $_SERVER['REQUEST_URI'] = '/' . ltrim($parts['path'], '/');
    }

    /**
     * @return void
     */
    protected static function resetDbal(): void
    {
        Closure::bind(
            static function (): void {
                /** @noinspection PhpUndefinedFieldInspection */
                static::$objects = null;
            },
            null,
            Dbal::class
        )();
    }

    /**
     * @param array $command
     * @return void
     */
    private static function runWpCliCommand(array $command): void
    {
        static $cliPath;
        $cliPath or $cliPath = getenv('VENDOR_DIR') . '/bin';

        array_unshift($command, "{$cliPath}/wp");
        $command[] = "--path=" . ABSPATH;
        $command[] = "--quiet";
        $command[] = "--skip-plugins";
        $command[] = "--skip-themes";
        $command[] = "--allow-root";

        [$dbHost, $dbName, $dbUser, $dbPwd] = self::loadEnvVars();
        $env = [
            'WORDPRESS_DB_HOST' => $dbHost,
            'WORDPRESS_DB_NAME' => $dbName,
            'WORDPRESS_DB_USER' => $dbUser,
            'WORDPRESS_DB_PASSWORD' => $dbPwd,
        ];

        $process = new Process($command, $cliPath, $env);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new Exception($process->getErrorOutput());
        }
    }

    /**
     * @return void
     */
    private static function initializeWp(): void
    {
        [$dbHost, $dbName, $dbUser, $dbPwd] = self::loadEnvVars();

        self::runWpCliCommand(
            [
                'config',
                'create',
                "--dbname={$dbName}",
                "--dbuser={$dbUser}",
                "--dbpass={$dbPwd}",
                "--dbhost={$dbHost}",
                '--force',
            ]
        );

        self::runWpCliCommand(['config', 'set', 'WP_DEBUG', 'true']);
        self::runWpCliCommand(['config', 'set', 'WP_DEBUG_LOG', 'false']);
        self::runWpCliCommand(['config', 'set', 'WP_DEBUG_DISPLAY', 'true']);
        self::runWpCliCommand(['config', 'set', 'SAVEQUERIES', 'true']);

        self::installDb();
    }

    /**
     * @return array
     */
    private static function loadEnvVars(): array
    {
        $testsEnv = getenv('TESTS_DIR') . '/.env';
        if (file_exists($testsEnv) && !getenv('WORDPRESS_DB_NAME')) {
            (new Dotenv(true))->load($testsEnv);
        }

        $dbHost = getenv('WORDPRESS_DB_HOST');
        $dbName = getenv('WORDPRESS_DB_NAME');
        $dbUser = getenv('WORDPRESS_DB_USER');
        $dbPwd = getenv('WORDPRESS_DB_PASSWORD');

        if (!$dbHost || !$dbName || !$dbUser || !$dbPwd) {
            throw new Exception('Could not initialize WP: missing env vars.');
        }

        return [$dbHost, $dbName, $dbUser, $dbPwd];
    }

    /**
     * @return void
     */
    private static function installDb(): void
    {
        self::runWpCliCommand(['db', 'reset', '--yes']);

        self::runWpCliCommand(
            [
                'core',
                'install',
                '--url=localhost',
                '--title=Fasti Test',
                '--admin_user=admin',
                '--admin_password=secret',
                '--admin_email=info@example.com',
            ]
        );
    }
}
