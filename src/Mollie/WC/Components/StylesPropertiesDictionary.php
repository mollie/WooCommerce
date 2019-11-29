<?php

class Mollie_WC_Components_StylesPropertiesDictionary
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
}
