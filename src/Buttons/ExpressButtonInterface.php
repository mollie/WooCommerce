<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons;

interface ExpressButtonInterface
{
    public function getId(): string;
    public function getButtonComponent(): string;
    // React component name
    /**
     * @return array<mixed>
     */
    public function getAjaxHandlers(): array;
    /**
     * @return array<mixed>
     */
    public function getScriptData(): array;
    public function canShow(): bool;
}
