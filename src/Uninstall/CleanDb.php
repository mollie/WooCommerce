<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Uninstall;

use Mollie\WooCommerce\Shared\SharedDataDictionary;
class CleanDb
{
    /**
     * @var array<string>
     */
    protected array $gatewayClassnames;
    /**
     * CleanDb constructor.
     *
     * @param array<string> $gatewayClassnames
     */
    public function __construct(array $gatewayClassnames)
    {
        $this->gatewayClassnames = $gatewayClassnames;
    }
    public function cleanAll(): void
    {
        $options = $this->allMollieOptionNames();
        $this->deleteSiteOptions($options);
        $this->cleanScheduledJobs();
    }
    /**
     * @param array<string> $options
     */
    protected function deleteSiteOptions(array $options): void
    {
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    protected function cleanScheduledJobs(): void
    {
        as_unschedule_action('mollie_woocommerce_cancel_unpaid_orders');
    }
    /**
     * @return array<string>
     */
    protected function allMollieOptionNames(): array
    {
        $names = SharedDataDictionary::MOLLIE_OPTIONS_NAMES;
        foreach ($this->gatewayClassnames as $gateway) {
            $option = strtolower($gateway) . "_settings";
            $names[] = $option;
        }
        return $names;
    }
}
