<?php

class Mollie_WC_Components_Styles
{
    const BASE_STYLE_KEY = 'base';
    const INVALID_STYLE_KEY = 'invalid';

    const BACKGROUND_COLOR = 'backgroundColor';
    const TEXT_COLOR = 'color';
    const FONT_SIZE = 'fontSize';
    const FONT_WEIGHT = 'fontWeight';
    const LETTER_SPACING = 'letterSpacing';
    const LINE_HEIGHT = 'lineHeight';
    const PADDING = 'padding';
    const TEXT_ALIGN = 'textAlign';
    const TEXT_TRANSFORM = 'textTransform';
    const INPUT_PLACEHOLDER = '::placeholder';

    const INVALID_TEXT_COLOR = 'invalid_color';
    const INVALID_BACKGROUND_COLOR = 'invalid_backgroundColor';

    const STYLES_OPTIONS_KEYS_MAP = [
        self::BACKGROUND_COLOR,
        self::TEXT_COLOR,
        self::FONT_SIZE,
        self::FONT_WEIGHT,
        self::LETTER_SPACING,
        self::LINE_HEIGHT,
        self::PADDING,
        self::TEXT_ALIGN,
        self::TEXT_TRANSFORM,
        self::INPUT_PLACEHOLDER,
    ];

    const INVALID_STYLES_OPTIONS_KEYS_MAP = [
        self::INVALID_TEXT_COLOR,
        self::INVALID_BACKGROUND_COLOR,
    ];

    protected $baseStyles;

    protected $invalidStyles;

    protected $defaults;

    public function __construct(array $options, array $defaults)
    {
        $this->defaults = $defaults;
        $this->baseStyles = $this->forBaseStatus($options);
        $this->invalidStyles = $this->forInvalidStatus($options);
    }

    public function all()
    {
        return [
            self::BASE_STYLE_KEY => $this->baseStyles,
            self::INVALID_STYLE_KEY => $this->invalidStyles,
        ];
    }

    protected function forBaseStatus(array $options)
    {
        return $this->fillStylesWithOptionValues(self::STYLES_OPTIONS_KEYS_MAP, $options);
    }

    protected function forInvalidStatus(array $options)
    {
        return $this->fillStylesWithOptionValues(
            array_merge(self::STYLES_OPTIONS_KEYS_MAP, self::INVALID_STYLES_OPTIONS_KEYS_MAP),
            $options
        );
    }

    protected function fillStylesWithOptionValues(array $definition, array $options)
    {
        $styles = [];
        foreach ($definition as $optionKey) {
            $styleKey = str_replace('invalid_', '', $optionKey);
            $styles[$styleKey] = isset($options[$optionKey])
                ? $options[$optionKey]
                : $this->defaultOptionValueFor($optionKey);
        }

        $styles = array_filter($styles);

        return $styles;
    }

    protected function defaultOptionValueFor($optionKey)
    {
        return isset($this->defaults[$optionKey]) ? $this->defaults[$optionKey] : null;
    }
}
