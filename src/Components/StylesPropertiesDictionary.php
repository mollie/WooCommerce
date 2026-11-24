<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Components;

class StylesPropertiesDictionary
{
    /**
     * @var string
     */
    public const BASE_STYLE_KEY = 'base';
    /**
     * @var string
     */
    public const INVALID_STYLE_KEY = 'invalid';
    /**
     * @var string
     */
    public const BACKGROUND_COLOR = 'backgroundColor';
    /**
     * @var string
     */
    public const TEXT_COLOR = 'color';
    /**
     * @var string
     */
    public const FONT_SIZE = 'fontSize';
    /**
     * @var string
     */
    public const FONT_WEIGHT = 'fontWeight';
    /**
     * @var string
     */
    public const LETTER_SPACING = 'letterSpacing';
    /**
     * @var string
     */
    public const LINE_HEIGHT = 'lineHeight';
    /**
     * @var string
     */
    public const PADDING = 'padding';
    /**
     * @var string
     */
    public const TEXT_ALIGN = 'textAlign';
    /**
     * @var string
     */
    public const TEXT_TRANSFORM = 'textTransform';
    /**
     * @var string
     */
    public const INPUT_PLACEHOLDER = '::placeholder';
    /**
     * @var string
     */
    public const INVALID_TEXT_COLOR = 'invalid_color';
    /**
     * @var string
     */
    public const INVALID_BACKGROUND_COLOR = 'invalid_backgroundColor';
    /**
     * @var string[]
     */
    public const STYLES_OPTIONS_KEYS_MAP = [self::BACKGROUND_COLOR, self::TEXT_COLOR, self::FONT_SIZE, self::FONT_WEIGHT, self::LETTER_SPACING, self::LINE_HEIGHT, self::PADDING, self::TEXT_ALIGN, self::TEXT_TRANSFORM, self::INPUT_PLACEHOLDER];
    /**
     * @var string[]
     */
    public const INVALID_STYLES_OPTIONS_KEYS_MAP = [self::INVALID_TEXT_COLOR, self::INVALID_BACKGROUND_COLOR];
}
