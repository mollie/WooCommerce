<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\Psr\Container\ContainerInterface;
abstract class AbstractSection
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
    abstract public function config(): array;
    public function styles(): string
    {
        return '';
    }
    public function images(): string
    {
        return $this->pluginUrl . '/public/images/';
    }
}
