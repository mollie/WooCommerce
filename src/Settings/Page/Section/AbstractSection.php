<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\Settings\Settings;

abstract class AbstractSection
{
    protected Settings $settings;
    protected string $pluginUrl;

    public function __construct(Settings $settings, string $pluginUrl)
    {
        $this->settings = $settings;
        $this->pluginUrl = $pluginUrl;
    }

    abstract public function config(): array;

    public function styles(): string{
        return '';
    }

    public function images(): string
    {
        return $this->pluginUrl . '/public/images/';
    }
}
