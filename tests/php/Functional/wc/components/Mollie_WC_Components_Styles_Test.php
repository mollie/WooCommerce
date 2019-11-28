<?php
use Mollie\WooCommerce\Tests\TestCase;

class Mollie_WC_Components_Styles_Test extends TestCase
{
    private $options;

    public function testForBaseStatus()
    {
        /*
         * Sut
         */
        $mollieComponentsStyle = new Mollie_WC_Components_Styles($this->options, []);

        /**
         * Execute Test
         */
        $result = $mollieComponentsStyle->all();

        self::assertEquals(
            [
                'backgroundColor' => $this->options[Mollie_WC_Components_Styles::BACKGROUND_COLOR],
                'color' => $this->options[Mollie_WC_Components_Styles::TEXT_COLOR],
                'fontSize' => $this->options[Mollie_WC_Components_Styles::FONT_SIZE],
                'fontWeight' => $this->options[Mollie_WC_Components_Styles::FONT_WEIGHT],
                'letterSpacing' => $this->options[Mollie_WC_Components_Styles::LETTER_SPACING],
                'lineHeight' => $this->options[Mollie_WC_Components_Styles::LINE_HEIGHT],
                'padding' => $this->options[Mollie_WC_Components_Styles::PADDING],
                'textAlign' => $this->options[Mollie_WC_Components_Styles::TEXT_ALIGN],
                'textTransform' => $this->options[Mollie_WC_Components_Styles::TEXT_TRANSFORM],
            ],
            $result[Mollie_WC_Components_Styles::BASE_STYLE_KEY]
        );
    }

    public function testForInvalidStatus()
    {
        /*
         * Sut
         */
        $mollieComponentsStyle = new Mollie_WC_Components_Styles($this->options, []);

        /**
         * Execute Test
         */
        $result = $mollieComponentsStyle->all();

        self::assertEquals(
            [
                'backgroundColor' => $this->options[Mollie_WC_Components_Styles::INVALID_BACKGROUND_COLOR],
                'color' => $this->options[Mollie_WC_Components_Styles::INVALID_TEXT_COLOR],
                'fontSize' => $this->options[Mollie_WC_Components_Styles::FONT_SIZE],
                'fontWeight' => $this->options[Mollie_WC_Components_Styles::FONT_WEIGHT],
                'letterSpacing' => $this->options[Mollie_WC_Components_Styles::LETTER_SPACING],
                'lineHeight' => $this->options[Mollie_WC_Components_Styles::LINE_HEIGHT],
                'padding' => $this->options[Mollie_WC_Components_Styles::PADDING],
                'textAlign' => $this->options[Mollie_WC_Components_Styles::TEXT_ALIGN],
                'textTransform' => $this->options[Mollie_WC_Components_Styles::TEXT_TRANSFORM],
            ],
            $result[Mollie_WC_Components_Styles::INVALID_STYLE_KEY]
        );
    }

    public function testAllStyles()
    {
        /*
         * Sut
         */
        $mollieComponentsStyle = new Mollie_WC_Components_Styles($this->options, []);

        /**
         * Execute Test
         */
        $result = $mollieComponentsStyle->all();

        self::assertEquals(true, array_key_exists('base', $result));
        self::assertEquals(true, array_key_exists('invalid', $result));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->options = [
            Mollie_WC_Components_Styles::BACKGROUND_COLOR => uniqid(),
            Mollie_WC_Components_Styles::TEXT_COLOR => uniqid(),
            Mollie_WC_Components_Styles::FONT_SIZE => uniqid(),
            Mollie_WC_Components_Styles::FONT_WEIGHT => uniqid(),
            Mollie_WC_Components_Styles::LETTER_SPACING => uniqid(),
            Mollie_WC_Components_Styles::LINE_HEIGHT => uniqid(),
            Mollie_WC_Components_Styles::PADDING => uniqid(),
            Mollie_WC_Components_Styles::TEXT_ALIGN => uniqid(),
            Mollie_WC_Components_Styles::TEXT_TRANSFORM => uniqid(),
            Mollie_WC_Components_Styles::INVALID_TEXT_COLOR => uniqid(),
            Mollie_WC_Components_Styles::INVALID_BACKGROUND_COLOR => uniqid(),
        ];
    }
}
