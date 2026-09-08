<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

/**
 * Class StaticIconProvider
 *
 * Implements the IconProviderInterface to provide a static set of icons.
 * The constructor accepts an arbitrary number of Icon objects which are stored internally.
 */
class StaticIconProvider implements IconProviderInterface
{
    /**
     * @var Icon[]
     */
    private array $icons;
    public function __construct(Icon ...$icons)
    {
        $this->icons = $icons;
    }
    /**
     * Provides access to the internal collection of icons passed during construction.
     *
     * @return Icon[] An array of Icon objects representing the provided icons.
     */
    public function provideIcons(): array
    {
        return $this->icons;
    }
}
