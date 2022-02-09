<?php

use Mollie\WooCommerce\Components\StylesPropertiesDictionary;

return [
    [
        'type' => 'title',
        'id' => 'mollie_components_styles',
        'title' => _x(
            'Base Styles',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
    ],
    StylesPropertiesDictionary::BACKGROUND_COLOR => [
        'type' => 'color',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::BACKGROUND_COLOR,
        'title' => _x('Background Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '#ffffff',
    ],
    StylesPropertiesDictionary::TEXT_COLOR => [
        'type' => 'color',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::TEXT_COLOR,
        'title' => _x('Text Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '#000000',
    ],
    StylesPropertiesDictionary::INPUT_PLACEHOLDER => [
        'type' => 'color',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::INPUT_PLACEHOLDER,
        'title' => _x('Placeholder Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '#cccccc',
    ],
    StylesPropertiesDictionary::FONT_SIZE => [
        'type' => 'text',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::FONT_SIZE,
        'title' => _x('Font Size', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'desc_tip' => _x(
            'Defines the component font size. Allowed units: \'em\', \'px\', \'rem\'.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '16px',
    ],
    StylesPropertiesDictionary::FONT_WEIGHT => [
        'type' => 'select',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::FONT_WEIGHT,
        'title' => _x('Font Weight', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => 'normal',
        'options' => [
            'lighter' => _x('Lighter', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'normal' => _x('Regular', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'bolder' => _x('Bold', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        ],
    ],
    StylesPropertiesDictionary::LETTER_SPACING => [
        'type' => 'number',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::LETTER_SPACING,
        'title' => _x('Letter Spacing', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '0',
    ],
    StylesPropertiesDictionary::LINE_HEIGHT => [
        'type' => 'number',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::LINE_HEIGHT,
        'title' => _x('Line Height', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '1.2',
        'custom_attributes' => [
            'step' => '.1',
        ],
    ],
    StylesPropertiesDictionary::PADDING => [
        'type' => 'text',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::PADDING,
        'title' => _x('Padding', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'desc_tip' => _x(
            'Add padding to the components. Allowed units include `16px 16px 16px 16px` and `em`, `px`, `rem`.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '.63em',
    ],
    StylesPropertiesDictionary::TEXT_ALIGN => [
        'type' => 'select',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::TEXT_ALIGN,
        'title' => _x('Align Text', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => 'left',
        'options' => [
            'left' => _x('Left', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'right' => _x('Right', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'center' => _x('Center', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'justify' => _x('Justify', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        ],
    ],
    StylesPropertiesDictionary::TEXT_TRANSFORM => [
        'type' => 'select',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::TEXT_TRANSFORM,
        'title' => _x('Transform Text ', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => 'none',
        'options' => [
            'none' => _x(
                'None',
                'Mollie Components Settings',
                'mollie-payments-for-woocommerce'
            ),
            'capitalize' => _x(
                'Capitalize',
                'Mollie Components Settings',
                'mollie-payments-for-woocommerce'
            ),
            'uppercase' => _x(
                'Uppercase',
                'Mollie Components Settings',
                'mollie-payments-for-woocommerce'
            ),
            'lowercase' => _x(
                'Lowercase',
                'Mollie Components Settings',
                'mollie-payments-for-woocommerce'
            ),
            'full-width' => _x(
                'Full Width',
                'Mollie Components Settings',
                'mollie-payments-for-woocommerce'
            ),
            'full-size-kana' => _x(
                'Full Size Kana',
                'Mollie Components Settings',
                'mollie-payments-for-woocommerce'
            ),
        ],
    ],
    [
        'type' => 'sectionend',
        'id' => 'mollie_components_styles',
    ],
    [
        'type' => 'title',
        'id' => 'mollie_components_invalid_styles',
        'title' => _x(
            'Invalid Status Styles',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
    ],
    StylesPropertiesDictionary::INVALID_TEXT_COLOR => [
        'type' => 'color',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::INVALID_TEXT_COLOR,
        'title' => _x('Text Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'desc_tip' => _x(
            'Text Color for invalid input.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '#000000',
    ],
    StylesPropertiesDictionary::INVALID_BACKGROUND_COLOR => [
        'type' => 'color',
        'id' => 'mollie_components_' . StylesPropertiesDictionary::INVALID_BACKGROUND_COLOR,
        'title' => _x('Background Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'desc_tip' => _x(
            'Background Color for invalid input.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '#FFF0F0',
    ],
    [
        'type' => 'sectionend',
        'id' => 'mollie_components_invalid_styles',
    ],
];
