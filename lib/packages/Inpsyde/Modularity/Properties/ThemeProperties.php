<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties;

/**
 * Class ThemeProperties
 *
 * @package Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties
 *
 * @psalm-suppress PossiblyFalseArgument, InvalidArgument
 */
class ThemeProperties extends BaseProperties
{
    /**
     * Additional properties specific for themes.
     */
    public const PROP_STATUS = 'status';
    public const PROP_TEMPLATE = 'template';
    /**
     * Available methods of Properties::__call()
     * from theme headers.
     *
     * @link https://developer.wordpress.org/reference/classes/wp_theme/
     */
    protected const HEADERS = [
        self::PROP_AUTHOR => 'Author',
        self::PROP_AUTHOR_URI => 'Author URI',
        self::PROP_DESCRIPTION => 'Description',
        self::PROP_DOMAIN_PATH => 'Domain Path',
        self::PROP_NAME => 'Theme Name',
        self::PROP_TEXTDOMAIN => 'Text Domain',
        self::PROP_URI => 'Theme URI',
        self::PROP_VERSION => 'Version',
        self::PROP_REQUIRES_WP => 'Requires at least',
        self::PROP_REQUIRES_PHP => 'Requires PHP',

        // additional headers
        self::PROP_STATUS => 'Status',
        self::PROP_TAGS => 'Tags',
        self::PROP_TEMPLATE => 'Template',
    ];

    /**
     * @param string $themeDirectory
     *
     * @return ThemeProperties
     */
    public static function new(string $themeDirectory): ThemeProperties
    {
        return new self($themeDirectory);
    }

    /**
     * ThemeProperties constructor.
     *
     * @param string $themeDirectory
     */
    protected function __construct(string $themeDirectory)
    {
        if (!function_exists('wp_get_theme')) {
            require_once ABSPATH . 'wp-includes/theme.php';
        }

        $theme = wp_get_theme($themeDirectory);
        $properties = Properties::DEFAULT_PROPERTIES;

        foreach (self::HEADERS as $key => $themeKey) {
            /** @psalm-suppress DocblockTypeContradiction */
            $properties[$key] = $theme->get($themeKey) ?? '';
        }

        $baseName = $theme->get_stylesheet();
        $basePath = $theme->get_template_directory();
        $baseUrl = (string) trailingslashit($theme->get_stylesheet_directory_uri());

        parent::__construct(
            $baseName,
            $basePath,
            $baseUrl,
            $properties
        );
    }

    /**
     * If the theme is published.
     *
     * @return string
     */
    public function status(): string
    {
        return (string) $this->get(self::PROP_STATUS);
    }

    public function template(): string
    {
        return (string) $this->get(self::PROP_TEMPLATE);
    }

    /**
     * @return bool
     */
    public function isChildTheme(): bool
    {
        return (bool) $this->template();
    }

    /**
     * @return bool
     */
    public function isCurrentTheme(): bool
    {
        return get_stylesheet() === $this->baseName();
    }

    /**
     * @return ThemeProperties|null
     */
    public function parentThemeProperties(): ?ThemeProperties
    {
        $template = $this->template();
        if (!$template) {
            return null;
        }

        $parent = wp_get_theme($template, get_theme_root($template));

        return static::new($parent->get_template_directory());
    }
}
