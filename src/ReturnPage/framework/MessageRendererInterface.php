<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Interface for rendering messages
 */
interface MessageRendererInterface
{
    /**
     * Render a message for the return page
     *
     * @param string $message
     * @param ReturnPageStatus $status
     * @return string HTML output
     */
    public function render(string $message, ReturnPageStatus $status): string;
}
