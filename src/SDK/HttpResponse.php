<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\SDK;

class HttpResponse
{
    public function setHttpResponseCode($statusCode): void
    {
        if (\PHP_SAPI !== 'cli' && !headers_sent()) {
            if (function_exists("http_response_code")) {
                http_response_code($statusCode);
            } else {
                header(" ", \true, $statusCode);
            }
        }
    }
}
