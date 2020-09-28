<?php

declare(strict_types=1);

namespace Inpsyde\Fasti\Tests;

use Brain\Faker;
use Brain\Monkey;
use DateTimeInterface;
use Inpsyde\Fasti\Application\Core\App;
use Inpsyde\Fasti\Application\Core\PluginProperties;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class UnitTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;

    public const PLUGIN_URL = 'http://www.example.com/wp-content/plugins/fasti/';

    /**
     * @var Faker\Providers
     */
    protected $wpFaker;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->mockConstants();

        Monkey\Functions\stubTranslationFunctions();
        Monkey\Functions\stubEscapeFunctions();
        $this->mockBaseFunctions();

        require_once ABSPATH . 'wp-includes/wp-db.php';
        global $wpdb;
        $wpdb = new DummyWpdb();

        /** @noinspection PhpUndefinedMethodInspection */
        $this->wpFaker = $this->faker->wp();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        \Brain\fakerReset();
        Monkey\tearDown();
        parent::tearDown();
    }

    protected function mockConstants(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            define('WP_PLUGIN_DIR', realpath(dirname(getenv('LIB_DIR'))) ?: getcwd());
        }
    }

    /**
     * @return void
     *
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel
     */
    protected function mockBaseFunctions(): void
    {
        // phpcs:enable Inpsyde.CodeQuality.NestingLevel

        Monkey\Functions\when('wp_normalize_path')->alias(
            static function (string $path): string {
                $path = preg_replace('|(?<=.)/+|', '/', str_replace('\\', '/', $path));

                return (($path[1] ?? '') === ':') ? ucfirst($path) : $path;
            }
        );

        Monkey\Functions\when('wp_generate_uuid4')->alias(
            function (): string {
                return (string)$this->faker->unique()->uuid;
            }
        );

        Monkey\Functions\when('get_option')->alias(
            static function (string $option): string {
                switch ($option) {
                    case 'date_format':
                        return DateTimeInterface::ATOM;
                    default:
                        return '';
                }
            }
        );

        Monkey\Functions\when('wp_cache_add_global_groups')->justReturn(null);
        Monkey\Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Europe/Rome'));
        Monkey\Functions\when('wp_kses_post')->returnArg();
        Monkey\Functions\when('wp_strip_all_tags')->alias('strip_tags');
        Monkey\Functions\when('plugins_url')->alias(
            static function (string $path = ''): string {
                $url = self::PLUGIN_URL;
                $path and $url .= '/' . ltrim($path, '/');

                return $url;
            }
        );
        Monkey\Functions\when('plugin_basename')->alias(
            static function (string $path): string {
                $pathParts = explode(DIRECTORY_SEPARATOR, dirname($path));

                return array_pop($pathParts) . '/index.php';
            }
        );
    }

    /**
     * @return App
     */
    protected function appContainer(?bool $debug = null, ?bool $assetsDebug = null): App
    {
        Monkey\Functions\expect('get_plugin_data')->atMost()->once()->andReturnUsing(
            function (): array {
                return $this->getPluginFileHeaders();
            }
        );

        $flags = PluginProperties::DEBUG_DEFAULT;
        if ($debug !== null) {
            $flags = $debug
                ? PluginProperties::FORCE_DEBUG
                : PluginProperties::NO_DEBUG;
        }
        if ($assetsDebug !== null) {
            $flags |= $assetsDebug
                ? PluginProperties::FORCE_ASSETS_DEBUG
                : PluginProperties::NO_ASSETS_DEBUG;
        }

        $properties = PluginProperties::new(getenv('LIB_DIR') . '/index.php', $flags);

        return App::new($properties);
    }

    /**
     * @param array $options
     * @return void
     */
    protected function mockOptions(array $options = []): void
    {
        OptionsMock::initialize($options);
    }

    /**
     * Simplified version of get_plugin_data(). We expect WordPress to don't break BC about this.
     *
     * @return array
     */
    private function getPluginFileHeaders(): array
    {
        $resource = fopen(getenv('LIB_DIR') . '/index.php', 'r');
        $data = fread($resource, 8192);
        fclose($resource);
        $data = str_replace("\r", "\n", $data);

        $headers = [
            'Name' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author',
            'AuthorURI' => 'Author URI',
            'TextDomain' => 'Text Domain',
            'DomainPath' => 'Domain Path',
            'Network' => 'Network',
            'RequiresWP' => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        ];

        $output = [];
        foreach ($headers as $key => $pattern) {
            preg_match('/^[ \t\/*#@]*' . preg_quote($pattern, '/') . ':(.*)$/mi', $data, $match);
            if (!empty($match[1])) {
                $output[$key] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
            }
        }

        return $output;
    }
}
