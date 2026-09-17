<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\Modularity\Properties;

/**
 * @phpstan-type ComposerAuthor array{
 *      name: string,
 *      email?: string,
 *      homepage?: string,
 *      role?: string,
 * }
 * @phpstan-type ComposerData array{
 *      name: string,
 *      version?: string,
 *      require?: array<string, string>,
 *      require-dev?: array<string, string>,
 *      description?: string,
 *      keywords?: string[],
 *      authors?: ComposerAuthor[],
 *      extra?: array{modularity?: array<string, string>},
 * }
 */
class LibraryProperties extends BaseProperties
{
    /** Allowed configuration in composer.json "extra.modularity" */
    public const EXTRA_KEYS = [self::PROP_DOMAIN_PATH, self::PROP_NAME, self::PROP_TEXTDOMAIN, self::PROP_URI, self::PROP_VERSION, self::PROP_REQUIRES_WP];
    /**
     * @param string $composerJsonFile
     * @param string|null $baseUrl
     *
     * @return LibraryProperties
     *
     * phpcs:disable SlevomatCodingStandard.Complexity
     */
    public static function new(string $composerJsonFile, ?string $baseUrl = null): LibraryProperties
    {
        // phpcs:enable SlevomatCodingStandard.Complexity
        $composerJsonData = self::readComposerJsonData($composerJsonFile);
        $properties = Properties::DEFAULT_PROPERTIES;
        $properties[self::PROP_DESCRIPTION] = $composerJsonData['description'] ?? '';
        $properties[self::PROP_TAGS] = $composerJsonData['keywords'] ?? [];
        $authors = $composerJsonData['authors'] ?? [];
        if (!is_array($authors)) {
            $authors = [];
        }
        $names = [];
        foreach ($authors as $author) {
            if (!is_array($author)) {
                continue;
            }
            $name = $author['name'] ?? '';
            if ($name !== '' && is_string($name)) {
                $names[] = $name;
            }
            $url = $author['homepage'] ?? '';
            if ($url !== '' && $properties[self::PROP_AUTHOR_URI] === '' && is_string($url)) {
                $properties[self::PROP_AUTHOR_URI] = $url;
            }
        }
        if (count($names) > 0) {
            $properties[self::PROP_AUTHOR] = implode(', ', $names);
        }
        // Custom settings which can be stored in composer.json "extra.modularity"
        $extra = $composerJsonData['extra']['modularity'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }
        foreach (self::EXTRA_KEYS as $key) {
            $properties[$key] = $extra[$key] ?? '';
        }
        // PHP requirement in composer.json "require" or "require-dev"
        $properties[self::PROP_REQUIRES_PHP] = self::extractPhpVersion($composerJsonData);
        // composer.json might have "version" in root
        $version = $composerJsonData['version'] ?? '';
        if ($version !== '' && is_string($version)) {
            $properties[self::PROP_VERSION] = $version;
        }
        [$baseName, $name] = static::buildNames($composerJsonData);
        $basePath = dirname($composerJsonFile);
        if ($properties[self::PROP_NAME] === '' || !is_string($properties[self::PROP_NAME])) {
            $properties[self::PROP_NAME] = $name;
        }
        return new self($baseName, $basePath, $baseUrl, $properties);
    }
    /**
     * @param string $url
     *
     * @return static
     */
    public function withBaseUrl(string $url): LibraryProperties
    {
        if ($this->baseUrl !== null) {
            throw new \Exception(sprintf('%s::$baseUrl property is not overridable.', __CLASS__));
        }
        $this->baseUrl = trailingslashit($url);
        return $this;
    }
    /**
     * @param ComposerData $composerJsonData
     *
     * @return array{string, string}
     */
    protected static function buildNames(array $composerJsonData): array
    {
        $composerName = (string) ($composerJsonData['name'] ?? '');
        $packageNamePieces = explode('/', $composerName, 2);
        $basename = implode('-', $packageNamePieces);
        // From "syde/foo-bar-baz" to  "Syde Foo Bar Baz"
        $name = mb_convert_case(str_replace(['-', '_', '.'], ' ', implode(' ', $packageNamePieces)), \MB_CASE_TITLE);
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
     * @param ComposerData $composerData
     * @param string $key
     *
     * @return string
     */
    protected static function extractPhpVersion(array $composerData, string $key = 'require'): string
    {
        $nextKey = $key === 'require' ? 'require-dev' : null;
        $base = $composerData[$key] ?? null;
        $requirement = is_array($base) ? $base['php'] ?? '' : '';
        $version = $requirement !== '' && is_string($requirement) ? trim($requirement) : '';
        if ($version === '') {
            return $nextKey !== null ? static::extractPhpVersion($composerData, $nextKey) : '';
        }
        // support for simpler requirements like `7.3`, `>=7.4` or alternative like `5.6 || >=7`
        $alternatives = explode('||', $version);
        /** @var non-empty-string|null $found */
        $found = null;
        foreach ($alternatives as $alternative) {
            $itemFound = static::parseVersion($alternative);
            if ($itemFound !== '' && ($found === null || version_compare($itemFound, $found, '<'))) {
                $found = $itemFound;
            }
        }
        if ($found !== null) {
            return $found;
        }
        return $nextKey !== null ? static::extractPhpVersion($composerData, $nextKey) : '';
    }
    /**
     * @param string $version
     *
     * @return string
     */
    protected static function parseVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '') {
            return '';
        }
        // versions range like `>= 7.2.4 < 8`
        if (preg_match('{>=?([\s0-9\.]+)<}', $version, $matches)) {
            return trim($matches[1], " \t\n\r\x00\v.");
        }
        // aliases like `dev-src#abcde as 7.4`
        if (preg_match('{as\s*([\s0-9\.]+)}', $version, $matches)) {
            return trim($matches[1], " \t\n\r\x00\v.");
        }
        // Basic requirements like 7.2, >=7.2, ^7.2, ~7.2
        if (preg_match('{^(?:[>=\s~\^]+)?([0-9\.]+)}', $version, $matches)) {
            return trim($matches[1], " \t\n\r\x00\v.");
        }
        return '';
    }
    /**
     * @param string $composerJsonFile
     *
     * @return ComposerData
     * @throws \Exception
     */
    private static function readComposerJsonData(string $composerJsonFile): array
    {
        if (!\is_file($composerJsonFile) || !\is_readable($composerJsonFile)) {
            throw new \Exception(esc_html("File {$composerJsonFile} does not exist or is not readable."));
        }
        $content = (string) file_get_contents($composerJsonFile);
        /** @var ComposerData $composerJsonData */
        $composerJsonData = json_decode($content, \true);
        if (json_last_error() !== \JSON_ERROR_NONE) {
            throw new \Exception(esc_html("Error reading file {$composerJsonFile}: " . json_last_error_msg()));
        }
        return $composerJsonData;
    }
}
