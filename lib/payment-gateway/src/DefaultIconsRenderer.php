<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

/**
 * Implements the GatewayIconsRendererInterface to render icons for payment gateways.
 * It uses an IconProviderInterface to provide icons and ensures that necessary CSS
 * is added to display them correctly.
 */
class DefaultIconsRenderer implements GatewayIconsRendererInterface
{
    private IconProviderInterface $iconProvider;
    public function __construct(IconProviderInterface $iconProvider)
    {
        $this->iconProvider = $iconProvider;
    }
    /**
     * Renders HTML containing payment gateway icons.
     * Ensures that necessary CSS is added to display the icons correctly
     * and formats them in batches if more than 4 icons are provided.
     *
     * @return string HTML content with rendered icons
     */
    public function renderIcons(): string
    {
        $html = '<span class="syde-gateway-icons">';
        foreach ($this->iconProvider->provideIcons() as $icon) {
            $html .= "<img alt='{$icon->alt()}' src='{$icon->src()}'>";
        }
        $html .= '</span>';
        return $html;
    }
}
