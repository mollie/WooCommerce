<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Activation\Migrations;

interface MigratorInterface
{
    /**
     * Plugin version at which this migration was introduced.
     * Used by ActivationModule to gate execution: a migrator runs iff
     * stored plugin version < targetVersion() <= current plugin version.
     */
    public function targetVersion(): string;
    public function migrate(): void;
}
