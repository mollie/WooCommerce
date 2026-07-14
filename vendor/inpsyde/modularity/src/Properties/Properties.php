<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Properties;

interface Properties
{
    public const PROP_AUTHOR = 'author';
    public const PROP_AUTHOR_URI = 'authorUri';
    public const PROP_DESCRIPTION = 'description';
    public const PROP_DOMAIN_PATH = 'domainPath';
    public const PROP_NAME = 'name';
    public const PROP_TEXTDOMAIN = 'textDomain';
    public const PROP_URI = 'uri';
    public const PROP_VERSION = 'version';
    public const PROP_REQUIRES_WP = 'requiresWp';
    public const PROP_REQUIRES_PHP = 'requiresPhp';
    public const PROP_TAGS = 'tags';
    public const DEFAULT_PROPERTIES = [self::PROP_AUTHOR => '', self::PROP_AUTHOR_URI => '', self::PROP_DESCRIPTION => '', self::PROP_DOMAIN_PATH => '', self::PROP_NAME => '', self::PROP_TEXTDOMAIN => '', self::PROP_URI => '', self::PROP_VERSION => '', self::PROP_REQUIRES_WP => null, self::PROP_REQUIRES_PHP => null, self::PROP_TAGS => []];
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);
    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;
    /**
     * @return bool
     */
    public function isDebug(): bool;
    /**
     * @return string
     */
    public function baseName(): string;
    /**
     * @return string
     */
    public function basePath(): string;
    /**
     * @return string|null
     */
    public function baseUrl(): ?string;
    /**
     * @return string
     */
    public function author(): string;
    /**
     * @return string
     */
    public function authorUri(): string;
    /**
     * @return string
     */
    public function description(): string;
    /**
     * @return string
     */
    public function textDomain(): string;
    /**
     * @return string
     */
    public function domainPath(): string;
    /**
     * The name of the plugin, theme or library.
     *
     * @return string
     */
    public function name(): string;
    /**
     * The home page of the plugin, theme or library.
     *
     * @return string
     */
    public function uri(): string;
    /**
     * @return string
     */
    public function version(): string;
    /**
     * Optional. Specify the minimum required WordPress version.
     *
     * @return string|null
     */
    public function requiresWp(): ?string;
    /**
     * Optional. Specify the minimum required PHP version.
     *
     * @return string|null
     */
    public function requiresPhp(): ?string;
    /**
     * Optional. Currently, only available for Theme and Library.
     * Plugins do not have support for "tags"/"keywords" in header.
     *
     * @return string[]
     *
     * @see https://developer.wordpress.org/reference/classes/wp_theme/#properties
     * @see https://getcomposer.org/doc/04-schema.md#keywords
     */
    public function tags(): array;
}
