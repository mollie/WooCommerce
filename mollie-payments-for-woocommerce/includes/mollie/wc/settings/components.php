<?php

class Mollie_WC_Settings_Components
{
    const STYLE_KEY_PREFIXES = [
        'invalid_',
    ];

    public function styles()
    {
        $defaults = $this->defaultSettings();
        $settings = [];

        $settings[Mollie_WC_Components_StylesPropertiesDictionary::BASE_STYLE_KEY] = $this->optionsFor(
            Mollie_WC_Components_StylesPropertiesDictionary::STYLES_OPTIONS_KEYS_MAP,
            $defaults
        );
        $settings[Mollie_WC_Components_StylesPropertiesDictionary::INVALID_STYLE_KEY] = $this->optionsFor(
            Mollie_WC_Components_StylesPropertiesDictionary::INVALID_STYLES_OPTIONS_KEYS_MAP,
            $defaults
        );

        return $settings;
    }

    protected function optionsFor($group, $defaults)
    {
        $settings = [];

        foreach ($group as $key) {
            $styleKey = str_replace(self::STYLE_KEY_PREFIXES, '', $key);
            $optionValue = get_option(
                "mollie_components_{$key}",
                $this->defaultOptionFor($defaults, $key)
            );
            $settings[$styleKey] = $optionValue;
        }

        return $settings;
    }

    protected function defaultSettings()
    {
        // TODO May be a function?
        $mollieComponentsFilePath = Mollie_WC_Plugin::getPluginPath(
            '/inc/settings/mollie_components.php'
        );

        if (!file_exists($mollieComponentsFilePath)) {
            return [];
        }

        $componentsFields = include $mollieComponentsFilePath;

        return (array)$componentsFields;
    }

    protected function defaultOptionFor($options, $key)
    {
        return isset($options[$key]['default']) ? $options[$key]['default'] : null;
    }
}
