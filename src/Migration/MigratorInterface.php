<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Migration;

interface MigratorInterface
{
    public function migrate(): void;
}
