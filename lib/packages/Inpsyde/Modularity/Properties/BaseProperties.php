<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties;

class BaseProperties implements Properties
{
    /**
     * @var null|bool
     */
    protected $isDebug = null;

    /**
     * @var string
     */
    protected $baseName;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string|null
     */
    protected $baseUrl;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @param string $baseName
     * @param string $basePath
     * @param string|null $baseUrl
     * @param array $properties
     */
    protected function __construct(
        string $baseName,
        string $basePath,
        string $baseUrl = null,
        array $properties = []
    ) {
        $baseName = $this->sanitizeBaseName($baseName);
        $basePath = (string) trailingslashit($basePath);
        if ($baseUrl) {
            $baseUrl = (string) trailingslashit($baseUrl);
        }

        $this->baseName = $baseName;
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
        $this->properties = array_replace(Properties::DEFAULT_PROPERTIES, $properties);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function sanitizeBaseName(string $name): string
    {
        substr_count($name, '/') and $name = dirname($name);

        return strtolower(pathinfo($name, PATHINFO_FILENAME));
    }

    /**
     * @return string
     */
    public function baseName(): string
    {
        return $this->baseName;
    }

    /**
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return string|null
     */
    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @return string
     */
    public function author(): string
    {
        return (string) $this->get(self::PROP_AUTHOR);
    }

    /**
     * @return string
     */
    public function authorUri(): string
    {
        return (string) $this->get(self::PROP_AUTHOR_URI);
    }

    /**
     * @return string
     */
    public function description(): string
    {
        return (string) $this->get(self::PROP_DESCRIPTION);
    }

    /**
     * @return string
     */
    public function textDomain(): string
    {
        return (string) $this->get(self::PROP_TEXTDOMAIN);
    }

    /**
     * @return string
     */
    public function domainPath(): string
    {
        return (string) $this->get(self::PROP_DOMAIN_PATH);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return (string) $this->get(self::PROP_NAME);
    }

    /**
     * @return string
     */
    public function uri(): string
    {
        return (string) $this->get(self::PROP_URI);
    }

    /**
     * @return string
     */
    public function version(): string
    {
        return (string) $this->get(self::PROP_VERSION);
    }

    /**
     * @return string|null
     */
    public function requiresWp(): ?string
    {
        $value = $this->get(self::PROP_REQUIRES_WP);

        return $value && is_string($value) ? $value : null;
    }

    /**
     * @return string|null
     */
    public function requiresPhp(): ?string
    {
        $value = $this->get(self::PROP_REQUIRES_PHP);

        return $value && is_string($value) ? $value : null;
    }

    /**
     * @return array
     */
    public function tags(): array
    {
        return (array) $this->get(self::PROP_TAGS);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    /**
     * @return bool
     * @see Properties::isDebug()
     */
    public function isDebug(): bool
    {
        if ($this->isDebug === null) {
            $this->isDebug = defined('WP_DEBUG') && WP_DEBUG;
        }

        return $this->isDebug;
    }
}
