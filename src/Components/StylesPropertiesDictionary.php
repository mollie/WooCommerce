<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Components;

class StylesPropertiesDictionary
{
    /**
     * @var string
     */
    const BASE_STYLE_KEY = 'base';
    /**
     * @var string
     */
    const INVALID_STYLE_KEY = 'invalid';

    /**
     * @var string
     */
    const BACKGROUND_COLOR = 'backgroundColor';
    /**
     * @var string
     */
    const TEXT_COLOR = 'color';
    /**
     * @var string
     */
    const FONT_SIZE = 'fontSize';
    /**
     * @var string
     */
    const FONT_WEIGHT = 'fontWeight';
    /**
     * @var string
     */
    const LETTER_SPACING = 'letterSpacing';
    /**
     * @var string
     */
    const LINE_HEIGHT = 'lineHeight';
    /**
     * @var string
     */
    const PADDING = 'padding';
    /**
     * @var string
     */
    const TEXT_ALIGN = 'textAlign';
    /**
     * @var string
     */
    const TEXT_TRANSFORM = 'textTransform';
    /**
     * @var string
     */
    const INPUT_PLACEHOLDER = '::placeholder';

    /**
     * @var string
     */
    const INVALID_TEXT_COLOR = 'invalid_color';
    /**
     * @var string
     */
    const INVALID_BACKGROUND_COLOR = 'invalid_backgroundColor';

    /**
     * @var string[]
     */
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

    /**
     * @var string[]
     */
    const INVALID_STYLES_OPTIONS_KEYS_MAP = [
        self::INVALID_TEXT_COLOR,
        self::INVALID_BACKGROUND_COLOR,
    ];
}
