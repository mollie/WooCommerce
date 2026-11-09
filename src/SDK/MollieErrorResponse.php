<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\SDK;

use Mollie\Api\Exceptions\ApiException;
/**
 * An error document from Mollie, raised as the SDK's ApiException.
 *
 * getMessage() carries the documentation link and the response body, as the plugin's log lines
 * always have. getPlainMessage() carries only the call and Mollie's detail, as it does for the
 * SDK's own exceptions, because that is the part a settings page may show.
 */
final class MollieErrorResponse extends ApiException
{
    /**
     * @param string $plainMessage The call and Mollie's detail, already escaped.
     * @param string $details The documentation link and response body, already escaped.
     */
    public function __construct(string $plainMessage, string $details, int $code, string $field)
    {
        parent::__construct($plainMessage . $details, $code, $field);
        $this->plainMessage = $plainMessage;
    }
}
