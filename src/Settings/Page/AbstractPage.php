<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Page\Section\AbstractSection;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\Psr\Container\ContainerInterface;
abstract class AbstractPage
{
    protected Settings $settings;
    protected string $pluginUrl;
    protected string $currentSection;
    protected bool $connectionStatus;
    protected bool $testModeEnabled;
    protected array $pages;
    protected Data $dataHelper;
    protected ContainerInterface $container;
    public function __construct(Settings $settings, string $pluginUrl, array $pages, string $currentSection, bool $connectionStatus, bool $testModeEnabled, Data $dataHelper, ContainerInterface $container)
    {
        $this->settings = $settings;
        $this->pluginUrl = $pluginUrl;
        $this->currentSection = $currentSection;
        $this->connectionStatus = $connectionStatus;
        $this->testModeEnabled = $testModeEnabled;
        $this->pages = $pages;
        $this->dataHelper = $dataHelper;
        $this->container = $container;
    }
    abstract public static function isTab(): bool;
    abstract public static function slug(): string;
    public static function tabName(): string
    {
        return 'tabName';
    }
    protected function sections(): array
    {
        return [];
    }
    public function settings(): array
    {
        $settings = [];
        $styles = [];
        foreach ($this->sections() as $sectionClass) {
            /** @var AbstractSection $section */
            $section = new $sectionClass($this->settings, $this->pluginUrl, $this->pages, $this->currentSection, $this->connectionStatus, $this->testModeEnabled, $this->dataHelper, $this->container);
            foreach ($section->config() as $field) {
                $settings[] = $field;
            }
            $styles[$sectionClass] = $section->styles();
        }
        array_unshift($settings, ['id' => $this->settings->getSettingId('styles'), 'type' => 'mollie_content', 'value' => implode($styles)]);
        return $settings;
    }
}
