<?php

//phpcs:disable

declare(strict_types=1);

class WC_Payment_Gateway
{
    public function get_option($key, $emptyValue = '')
    {
        return '';
    }

    public function init_settings(): void
    {
    }

    public function display_errors(): void
    {}

    public function process_admin_options(): bool
    {
        return true;
    }

    public function get_return_url()
    {
    }

    public function is_available()
    {
    }
}
