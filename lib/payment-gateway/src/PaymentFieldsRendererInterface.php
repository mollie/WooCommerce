<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Throwable;
interface PaymentFieldsRendererInterface
{
    /**
     * Renders the payment fields.
     *
     * @return string Rendered HTML.
     * @throws Throwable
     */
    public function renderFields(): string;
}
