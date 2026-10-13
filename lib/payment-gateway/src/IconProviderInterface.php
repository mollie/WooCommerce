<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

/**
 * Interface for providing icons to the payment gateway system.
 *
 * Implementations of this interface are responsible for returning an array of `Icon` objects,
 * which can be used to represent different payment methods or statuses visually within the UI.
 */
interface IconProviderInterface
{
    /**
     * Returns an array of icons provided by the implementation.
     *
     * @return Icon[]
     *      An array containing instances of the `Icon` class, each representing a specific icon.
     */
    public function provideIcons(): array;
}
