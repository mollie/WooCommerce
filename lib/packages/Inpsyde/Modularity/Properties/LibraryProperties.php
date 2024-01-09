<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties;

/**
 * Class LibraryProperties
 *
 * @package Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties
 */
class LibraryProperties extends BaseProperties
{
    /**
     * Allowed configuration in composer.json "extra.modularity".
     *
     * @var array
     */
    public const EXTRA_KEYS = [
        self::PROP_DOMAIN_PATH,
        self::PROP_NAME,
        self::PROP_TEXTDOMAIN,
        self::PROP_URI,
        self::PROP_VERSION,
        self::PROP_REQUIRES_WP,
    ];

    /**
     * @param string $composerJsonFile
     * @param string|null $baseUrl
     *
     * @return LibraryProperties
     *
     * @throws \Exception
     * @psalm-suppress MixedArrayAccess
     */
    public static function new(string $composerJsonFile, ?string $baseUrl = null): LibraryProperties
    {
        if (!\is_file($composerJsonFile) || !\is_readable($composerJsonFile)) {
            throw new \Exception("File {$composerJsonFile} does not exist or is not readable.");
        }

        $content = (string) file_get_contents($composerJsonFile);
        /** @var array $composerJsonData */
        $composerJsonData = json_decode($content, true);

        $properties = Properties::DEFAULT_PROPERTIES;
        $properties[self::PROP_DESCRIPTION] = $composerJsonData['description'] ?? '';
        $properties[self::PROP_TAGS] = $composerJsonData['keywords'] ?? [];

        $authors = $composerJsonData['authors'] ?? [];
        $names = [];
        foreach ((array) $authors as $author) {
            $name = $author['name'] ?? null;
            if ($name && is_string($name)) {
                $names[] = $name;
            }
            $url = $author['homepage'] ?? null;
            if ($url && !$properties['authorUri'] && is_string($url)) {
                $properties[self::PROP_AUTHOR_URI] = $url;
            }
        }
        if (count($names) > 0) {
            $properties[self::PROP_AUTHOR] = implode(', ', $names);
        }

        // Custom settings which can be stored in composer.json "extra.modularity"
        $extra = $composerJsonData['extra']['modularity'] ?? [];
        foreach (self::EXTRA_KEYS as $key) {
            $properties[$key] = $extra[$key] ?? '';
        }

        // PHP requirement in composer.json "require" or "require-dev"
        $properties[self::PROP_REQUIRES_PHP] = self::extractPhpVersion($composerJsonData);

        // composer.json might have "version" in root
        $version = $composerJsonData['version'] ?? null;
        if ($version && is_string($version)) {
            $properties[self::PROP_VERSION] = $version;
        }

        [$baseName, $name] = static::buildNames($composerJsonData);
        $basePath = dirname($composerJsonFile);
        if (empty($properties[self::PROP_NAME])) {
            $properties[self::PROP_NAME] = $name;
        }

        return new self(
            $baseName,
            $basePath,
            $baseUrl,
            $properties
        );
    }

    /**
     * @param array $composerJsonData
     *
     * @return array{string, string}
     */
    private static function buildNames(array $composerJsonData): array
    {
        $composerName = (string) ($composerJsonData['name'] ?? '');
        $packageNamePieces = explode('/', $composerName, 2);
        $basename = implode('-', $packageNamePieces);
        // "inpsyde/foo-bar-baz" => "Inpsyde Foo Bar Baz"
        $name = mb_convert_case(
            str_replace(['-', '_', '.'], ' ', implode(' ', $packageNamePieces)),
            MB_CASE_TITLE
        );

        return [$basename, $name];
    }

    /**
     * Check PHP version in require, require-dev.
     *
     * Attempt to parse requirements to find the _minimum_ accepted version (consistent with WP).
     * Composer requirements are parsed in a way that, for example:
     * `>=7.2`         returns `7.2`
     * `^7.3`          returns `7.3`
     * `5.6 || >= 7.1` returns `5.6`
     * `>= 7.1 < 8`    returns `7.1`
     *
     * @param array $composerData
     * @param string $key
     *
     * @return string|null
     */
    private static function extractPhpVersion(array $composerData, string $key = 'require'): ?string
    {
        $nextKey = ($key === 'require')
            ? 'require-dev'
            : null;
        $base = (array) ($composerData[$key] ?? []);
        $requirement = $base['php'] ?? null;
        $version = ($requirement && is_string($requirement))
            ? trim($requirement)
            : null;
        if (!$version) {
            return $nextKey
                ? static::extractPhpVersion($composerData, $nextKey)
                : null;
        }

        static $matcher;
        $matcher or $matcher = static function (string $version): ?string {
            $version = trim($version);
            if (!$version) {
                return null;
            }

            // versions range like `>= 7.2.4 < 8`
            if (preg_match('{>=?([\s0-9\.]+)<}', $version, $matches)) {
                return trim($matches[1], " \t\n\r\0\x0B.");
            }

            // aliases like `dev-src#abcde as 7.4`
            if (preg_match('{as\s*([\s0-9\.]+)}', $version, $matches)) {
                return trim($matches[1], " \t\n\r\0\x0B.");
            }

            // Basic requirements like 7.2, >=7.2, ^7.2, ~7.2
            if (preg_match('{^(?:[>=\s~\^]+)?([0-9\.]+)}', $version, $matches)) {
                return trim($matches[1], " \t\n\r\0\x0B.");
            }

            return null;
        };

        // support for simpler requirements like `7.3`, `>=7.4` or alternative like `5.6 || >=7`

        $alternatives = explode('||', $version);
        $found = null;
        foreach ($alternatives as $alternative) {
            /** @var callable(string):?string $matcher */
            $itemFound = $matcher($alternative);
            if ($itemFound && (!$found || version_compare($itemFound, $found, '<'))) {
                $found = $itemFound;
            }
        }

        if ($found) {
            return $found;
        }

        return $nextKey
            ? static::extractPhpVersion($composerData, $nextKey)
            : null;
    }

    /**
     * @param string $url
     *
     * @return static
     *
     * @throws \Exception
     */
    public function withBaseUrl(string $url): LibraryProperties
    {
        if ($this->baseUrl !== null) {
            throw new \Exception(sprintf('%s::$baseUrl property is not overridable.', __CLASS__));
        }

        $this->baseUrl = trailingslashit($url);

        return $this;
    }
}
