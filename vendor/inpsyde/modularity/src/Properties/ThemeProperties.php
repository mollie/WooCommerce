<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Properties;

class ThemeProperties extends BaseProperties
{
    public const PROP_STATUS = 'status';
    public const PROP_TEMPLATE = 'template';
    /** @see https://developer.wordpress.org/reference/classes/wp_theme/ */
    protected const HEADERS = [
        self::PROP_AUTHOR => 'Author',
        self::PROP_AUTHOR_URI => 'AuthorURI',
        self::PROP_DESCRIPTION => 'Description',
        self::PROP_DOMAIN_PATH => 'DomainPath',
        self::PROP_NAME => 'Name',
        self::PROP_TEXTDOMAIN => 'TextDomain',
        self::PROP_URI => 'ThemeURI',
        self::PROP_VERSION => 'Version',
        self::PROP_REQUIRES_WP => 'RequiresWP',
        self::PROP_REQUIRES_PHP => 'RequiresPHP',
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
     * @param string $themeDirectory
     */
    protected function __construct(string $themeDirectory)
    {
        if (!function_exists('wp_get_theme')) {
            require_once \ABSPATH . 'wp-includes/theme.php';
        }
        $theme = wp_get_theme($themeDirectory);
        $properties = Properties::DEFAULT_PROPERTIES;
        foreach (self::HEADERS as $key => $themeKey) {
            $property = $theme->get($themeKey);
            if (is_string($property) || is_array($property)) {
                $properties[$key] = $property;
            }
        }
        $baseName = $theme->get_stylesheet();
        $basePath = $theme->get_stylesheet_directory();
        $baseUrl = trailingslashit($theme->get_stylesheet_directory_uri());
        parent::__construct($baseName, $basePath, $baseUrl, $properties);
    }
    /**
     * @return string
     */
    public function status(): string
    {
        return (string) $this->get(self::PROP_STATUS);
    }
    /**
     * @return string
     */
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
        if ($template === '') {
            return null;
        }
        $parent = wp_get_theme($template, get_theme_root($template));
        return static::new($parent->get_template_directory());
    }
}
