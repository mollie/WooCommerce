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
        $mollieComponentsStyle = new Mollie_WC_Components_Stylesproperties($this->options, []);

        /**
         * Execute Test
         */
        $result = $mollieComponentsStyle->all();

        self::assertEquals(
            [
                'backgroundColor' => $this->options[Mollie_WC_Components_Stylesproperties::BACKGROUND_COLOR],
                'color' => $this->options[Mollie_WC_Components_Stylesproperties::TEXT_COLOR],
                'fontSize' => $this->options[Mollie_WC_Components_Stylesproperties::FONT_SIZE],
                'fontWeight' => $this->options[Mollie_WC_Components_Stylesproperties::FONT_WEIGHT],
                'letterSpacing' => $this->options[Mollie_WC_Components_Stylesproperties::LETTER_SPACING],
                'lineHeight' => $this->options[Mollie_WC_Components_Stylesproperties::LINE_HEIGHT],
                'padding' => $this->options[Mollie_WC_Components_Stylesproperties::PADDING],
                'textAlign' => $this->options[Mollie_WC_Components_Stylesproperties::TEXT_ALIGN],
                'textTransform' => $this->options[Mollie_WC_Components_Stylesproperties::TEXT_TRANSFORM],
            ],
            $result[Mollie_WC_Components_Stylesproperties::BASE_STYLE_KEY]
        );
    }

    public function testForInvalidStatus()
    {
        /*
         * Sut
         */
        $mollieComponentsStyle = new Mollie_WC_Components_Stylesproperties($this->options, []);

        /**
         * Execute Test
         */
        $result = $mollieComponentsStyle->all();

        self::assertEquals(
            [
                'backgroundColor' => $this->options[Mollie_WC_Components_Stylesproperties::INVALID_BACKGROUND_COLOR],
                'color' => $this->options[Mollie_WC_Components_Stylesproperties::INVALID_TEXT_COLOR],
                'fontSize' => $this->options[Mollie_WC_Components_Stylesproperties::FONT_SIZE],
                'fontWeight' => $this->options[Mollie_WC_Components_Stylesproperties::FONT_WEIGHT],
                'letterSpacing' => $this->options[Mollie_WC_Components_Stylesproperties::LETTER_SPACING],
                'lineHeight' => $this->options[Mollie_WC_Components_Stylesproperties::LINE_HEIGHT],
                'padding' => $this->options[Mollie_WC_Components_Stylesproperties::PADDING],
                'textAlign' => $this->options[Mollie_WC_Components_Stylesproperties::TEXT_ALIGN],
                'textTransform' => $this->options[Mollie_WC_Components_Stylesproperties::TEXT_TRANSFORM],
            ],
            $result[Mollie_WC_Components_Stylesproperties::INVALID_STYLE_KEY]
        );
    }

    public function testAllStyles()
    {
        /*
         * Sut
         */
        $mollieComponentsStyle = new Mollie_WC_Components_Stylesproperties($this->options, []);

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
            Mollie_WC_Components_Stylesproperties::BACKGROUND_COLOR => uniqid(),
            Mollie_WC_Components_Stylesproperties::TEXT_COLOR => uniqid(),
            Mollie_WC_Components_Stylesproperties::FONT_SIZE => uniqid(),
            Mollie_WC_Components_Stylesproperties::FONT_WEIGHT => uniqid(),
            Mollie_WC_Components_Stylesproperties::LETTER_SPACING => uniqid(),
            Mollie_WC_Components_Stylesproperties::LINE_HEIGHT => uniqid(),
            Mollie_WC_Components_Stylesproperties::PADDING => uniqid(),
            Mollie_WC_Components_Stylesproperties::TEXT_ALIGN => uniqid(),
            Mollie_WC_Components_Stylesproperties::TEXT_TRANSFORM => uniqid(),
            Mollie_WC_Components_Stylesproperties::INVALID_TEXT_COLOR => uniqid(),
            Mollie_WC_Components_Stylesproperties::INVALID_BACKGROUND_COLOR => uniqid(),
        ];
    }
}
