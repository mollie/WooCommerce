<?php

//phpcs:disable

declare(strict_types=1);

class WC_Payment_Gateway
{
    public $id;
    public $enabled;

    public function __construct($id = 1, $enabled = 'yes')
    {
        $this->id = $id;
        $this->enabled = $enabled;
    }
    public function get_option($key, $emptyValue = '')
    {
        return '';
    }

    public function init_settings()
    {
    }

    public function display_errors()
    {}

    public function process_admin_options()
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
