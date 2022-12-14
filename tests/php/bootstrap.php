<?php # -*- coding: utf-8 -*-

$projectDir = dirname(dirname(__DIR__));
$vendor = "{$projectDir}/vendor/";

if (!file_exists($vendor . 'autoload.php')) {
    die('Please install via Composer before running tests.');
}

require_once __DIR__ . '/Stubs/stubs.php';
require_once __DIR__ . '/Stubs/Mollie/Order.php';
require_once $vendor . 'brain/monkey/inc/patchwork-loader.php';
require_once $vendor . 'autoload.php';

define('PROJECT_DIR', $projectDir);
define('TEST_PATH', __DIR__);

if (!defined('M4W_PLUGIN_DIR')) {
    define('M4W_PLUGIN_DIR', PROJECT_DIR);
}
if (!defined('M4W_PLUGIN_URL')) {
    define('M4W_PLUGIN_URL', PROJECT_DIR);
}

unset($vendor, $projectDir);


require_once PROJECT_DIR . '/inc/functions.php';
