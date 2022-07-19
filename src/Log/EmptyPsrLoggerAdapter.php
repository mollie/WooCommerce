<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Log;

use Psr\Log\AbstractLogger;

class EmptyPsrLoggerAdapter extends AbstractLogger
{

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
    }

}
