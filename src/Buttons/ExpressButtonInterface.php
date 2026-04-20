<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons;

interface ExpressButtonInterface
{
    public function getId(): string;
    public function getButtonComponent(): string;
    // React component name
    public function getAjaxHandlers(): array;
    public function getScriptData(): array;
    public function canShow(): bool;
}
