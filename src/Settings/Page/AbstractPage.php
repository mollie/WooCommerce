<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Page\Section\AbstractSection;
use Mollie\WooCommerce\Settings\Settings;

abstract class AbstractPage
{
    protected Settings $settings;
    protected string $pluginUrl;

    public function __construct(Settings $settings, string $pluginUrl)
    {
        $this->settings = $settings;
        $this->pluginUrl = $pluginUrl;
    }

    abstract public function isTab(): bool;

    abstract public function slug(): string;

    public function tabName(): string
    {
        return '';
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
            $section = new $sectionClass($this->settings, $this->pluginUrl);
            foreach ($section->config() as $field) {
                $settings[] = $field;
            }
            $styles[$sectionClass] = preg_replace('/\s+/', '', $section->styles());
        }
        array_unshift($settings, [
            'id' => $this->settings->getSettingId('styles'),
            'type' => 'mollie_content',
            'value' => implode($styles)
        ]);
        return $settings;
    }
}
