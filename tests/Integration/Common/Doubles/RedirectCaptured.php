<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Doubles;

/**
 * Thrown from the wp_redirect filter, so a handler that redirects and exits can be observed under
 * PHPUnit: the location is captured and the exit is never reached.
 */
final class RedirectCaptured extends \RuntimeException
{
    private string $location;

    public function __construct(string $location)
    {
        parent::__construct('Redirect to ' . $location);
        $this->location = $location;
    }

    public static function listen(): callable
    {
        return static function ($location): void {
            throw new self((string) $location);
        };
    }

    public function location(): string
    {
        return $this->location;
    }
}
