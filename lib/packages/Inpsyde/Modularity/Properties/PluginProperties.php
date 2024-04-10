<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties;

/**
 * Class PluginProperties
 *
 * @package Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties
 *
 * @psalm-suppress PossiblyFalseArgument, InvalidArgument
 */
class PluginProperties extends BaseProperties
{
    /**
     * Custom properties for Plugins.
     */
    public const PROP_NETWORK = 'network';
    public const PROP_REQUIRES_PLUGINS = 'requiresPlugins';
    /**
     * Available methods of Properties::__call()
     * from plugin headers.
     *
     * @link https://developer.wordpress.org/reference/functions/get_plugin_data/
     */
    protected const HEADERS = [
        self::PROP_AUTHOR => 'Author',
        self::PROP_AUTHOR_URI => 'AuthorURI',
        self::PROP_DESCRIPTION => 'Description',
        self::PROP_DOMAIN_PATH => 'DomainPath',
        self::PROP_NAME => 'Name',
        self::PROP_TEXTDOMAIN => 'TextDomain',
        self::PROP_URI => 'PluginURI',
        self::PROP_VERSION => 'Version',
        self::PROP_REQUIRES_WP => 'RequiresWP',
        self::PROP_REQUIRES_PHP => 'RequiresPHP',

        // additional headers
        self::PROP_NETWORK => 'Network',
        self::PROP_REQUIRES_PLUGINS => 'RequiresPlugins',
    ];

    /**
     * @var string
     */
    private $pluginMainFile;

    /**
     * @var string
     */
    private $pluginBaseName;

    /**
     * @var bool|null
     */
    protected $isMu;

    /**
     * @var bool|null
     */
    protected $isActive;

    /**
     * @var bool|null
     */
    protected $isNetworkActive;

    /**
     * @param string $pluginMainFile
     *
     * @return PluginProperties
     */
    public static function new(string $pluginMainFile): PluginProperties
    {
        return new self($pluginMainFile);
    }

    /**
     * PluginProperties constructor.
     *
     * @param string $pluginMainFile
     */
    protected function __construct(string $pluginMainFile)
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // $markup = false, to avoid an incorrect early wptexturize call. Also we probably don't want HTML here anyway
        // @see https://core.trac.wordpress.org/ticket/49965
        $pluginData = get_plugin_data($pluginMainFile, false);
        $properties = Properties::DEFAULT_PROPERTIES;

        // Map pluginData to internal structure.
        foreach (self::HEADERS as $key => $pluginDataKey) {
            $properties[$key] = $pluginData[$pluginDataKey] ?? '';
            unset($pluginData[$pluginDataKey]);
        }
        $properties = array_merge($properties, $pluginData);

        $this->pluginMainFile = wp_normalize_path($pluginMainFile);

        $this->pluginBaseName = plugin_basename($pluginMainFile);
        $basePath = plugin_dir_path($pluginMainFile);
        $baseUrl = plugins_url('/', $pluginMainFile);

        parent::__construct(
            $this->pluginBaseName,
            $basePath,
            $baseUrl,
            $properties
        );
    }

    /**
     * @return string
     */
    public function pluginMainFile(): string
    {
        return $this->pluginMainFile;
    }

    /**
     * @return bool
     *
     * @psalm-suppress PossiblyFalseArgument
     */
    public function network(): bool
    {
        return (bool) $this->get(self::PROP_NETWORK, false);
    }

    /**
     * @return array
     */
    public function requiresPlugins(): array
    {
        $value = $this->get(self::PROP_REQUIRES_PLUGINS);

        return $value && is_string($value) ? explode(',', $value) : [];
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        if ($this->isActive === null) {
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->isActive = is_plugin_active($this->pluginBaseName);
        }

        return $this->isActive;
    }

    /**
     * @return bool
     */
    public function isNetworkActive(): bool
    {
        if ($this->isNetworkActive === null) {
            if (!function_exists('is_plugin_active_for_network')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->isNetworkActive = is_plugin_active_for_network($this->pluginBaseName);
        }

        return $this->isNetworkActive;
    }

    /**
     * @return bool
     */
    public function isMuPlugin(): bool
    {
        if ($this->isMu === null) {
            /**
             * @psalm-suppress UndefinedConstant
             * @psalm-suppress MixedArgument
             */
            $muPluginDir = wp_normalize_path(WPMU_PLUGIN_DIR);
            $this->isMu = strpos($this->pluginMainFile, $muPluginDir) === 0;
        }

        return $this->isMu;
    }
}
