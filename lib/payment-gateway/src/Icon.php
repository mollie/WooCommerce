<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

/**
 * Represents an icon with a unique identifier, source URL, and alternative text.
 * This class encapsulates the essential attributes of an icon used in user interfaces,
 * ensuring that each instance has a distinct ID, a valid source path,
 * and descriptive alt text for accessibility.
 */
final class Icon
{
    private string $id;
    private string $src;
    private string $alt;
    public function __construct(string $id, string $src, string $alt)
    {
        $this->id = $id;
        $this->src = $src;
        $this->alt = $alt;
    }
    /**
     * Returns the unique identifier of the icon.
     *
     * @return string The icon's ID.
     */
    public function id(): string
    {
        return $this->id;
    }
    /**
     * Retrieves the source URL of the icon image.
     *
     * @return string The icon's source path.
     */
    public function src(): string
    {
        return $this->src;
    }
    /**
     * Provides the alternative text for the icon, used for accessibility purposes.
     *
     * @return string Descriptive alt text for the icon.
     */
    public function alt(): string
    {
        return $this->alt;
    }
}
