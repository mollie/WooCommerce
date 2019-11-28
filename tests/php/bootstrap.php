<?php # -*- coding: utf-8 -*-

$vendor = dirname(__DIR__, 2) . '/vendor/';
if (!file_exists($vendor . 'autoload.php')) {
    die('Please install via Composer before running tests.');
}

require_once __DIR__ . '/Stubs/stubs.php';
require_once $vendor . 'brain/monkey/inc/patchwork-loader.php';
require_once $vendor . 'autoload.php';
unset($vendor);

define('PROJECT_DIR', dirname(__DIR__, 2));
define('TEST_PATH', __DIR__);

if (!defined('M4W_PLUGIN_DIR')) {
    define('M4W_PLUGIN_DIR', PROJECT_DIR . '/mollie-payments-for-woocommerce');
}
if (!defined('M4W_PLUGIN_URL')) {
    define('M4W_PLUGIN_URL', PROJECT_DIR . '/mollie-payments-for-woocommerce');
}

putenv('PROJECT_DIR=' . PROJECT_DIR);
putenv('TESTS_PATH=' . TEST_PATH);
