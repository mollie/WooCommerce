<?php

// TODO Remember validation.

return [
    'mollie_components' => [
        'type' => 'title',
        'title' => _x(
            'Mollie Components',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'description' => _x(
            'Mollie Components is a set of Javascript APIs that allow you to add the fields needed for credit card holder data to your own checkout.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
    ],
    Mollie_WC_Components_Styles::BACKGROUND_COLOR => [
        'type' => 'color',
        'title' => _x('Background Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '#ffffff',
    ],
    Mollie_WC_Components_Styles::TEXT_COLOR => [
        'type' => 'color',
        'title' => _x('Text Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '#000000',
    ],
    Mollie_WC_Components_Styles::INPUT_PLACEHOLDER => [
        'type' => 'color',
        'title' => _x('Placeholder Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '#cccccc',
    ],
    Mollie_WC_Components_Styles::FONT_SIZE => [
        'type' => 'text',
        'title' => _x('Font Size', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'description' => _x(
            'Font size define the size for the font in the components. `em`, `px`, `rem` units are allowed.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '16px',
    ],
    Mollie_WC_Components_Styles::FONT_WEIGHT => [
        'type' => 'select',
        'title' => _x('Font Weight', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => 'normal',
        'options' => [
            'lighter' => _x('Lighter', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'normal' => _x('Normal', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'bolder' => _x('Bold', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        ],
    ],
    Mollie_WC_Components_Styles::LETTER_SPACING => [
        'type' => 'number',
        'title' => _x('Letter Spacing', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '0',
    ],
    Mollie_WC_Components_Styles::LINE_HEIGHT => [
        'type' => 'number',
        'title' => _x('Line Height', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'default' => '1.2',
        'custom_attributes' => [
            'step' => '.1',
        ],
    ],
    Mollie_WC_Components_Styles::PADDING => [
        'type' => 'string',
        'title' => _x('Padding', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'description' => _x(
            'Add padding to the components. Eg. `16px 16px 16px 16px` and `em`, `px`, `rem` units are allowed.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '.63em',
    ],
    Mollie_WC_Components_Styles::TEXT_ALIGN => [
        'type' => 'select',
        'title' => _x('Text Align', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'options' => [
            'left' => _x('Left', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'right' => _x('Right', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'center' => _x('Center', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
            'justify' => _x('Justify', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        ],
    ],
    Mollie_WC_Components_Styles::TEXT_TRANSFORM => [
        'type' => 'select',
        'title' => _x('Text Transform', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
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
    // TODO Check again for description
    'mollie_components_invalid' => [
        'type' => 'title',
        'title' => _x(
            'Invalid Status',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'description' => _x(
            'Input fields for invalid data entry status.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
    ],
    Mollie_WC_Components_Styles::INVALID_TEXT_COLOR => [
        'type' => 'color',
        'title' => _x('Text Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'description' => _x(
            'Text Color for invalid input.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '#000000',
    ],
    Mollie_WC_Components_Styles::INVALID_BACKGROUND_COLOR => [
        'type' => 'color',
        'title' => _x('Background Color', 'Mollie Components Settings', 'mollie-payments-for-woocommerce'),
        'description' => _x(
            'Background Color for invalid input.',
            'Mollie Components Settings',
            'mollie-payments-for-woocommerce'
        ),
        'default' => '#FFF0F0',
    ],
];
