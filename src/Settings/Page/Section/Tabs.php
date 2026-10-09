<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

class Tabs extends \Mollie\WooCommerce\Settings\Page\Section\AbstractSection
{
    public function config(): array
    {
        return [['id' => $this->settings->getSettingId('notices'), 'type' => 'mollie_content', 'value' => $this->content()]];
    }
    protected function content(): string
    {
        $content = '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';
        foreach ($this->pages as $pageClassName) {
            if ($pageClassName::isTab()) {
                $currentTabClass = '';
                if ($pageClassName::slug() === $this->currentSection) {
                    $currentTabClass = 'nav-tab-active';
                }
                $content .= '<a class="nav-tab ' . $currentTabClass . '" href="' . $this->pageUrl($pageClassName::slug()) . '">';
                $content .= $pageClassName::tabName();
                $content .= '</a>';
            }
        }
        $content .= '</nav>';
        return $content;
    }
    protected function pageUrl(string $sectionId): string
    {
        return admin_url('admin.php?page=wc-settings&tab=mollie_settings&section=' . sanitize_title($sectionId));
    }
}
