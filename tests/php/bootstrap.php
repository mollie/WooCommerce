<?php # -*- coding: utf-8 -*-

$projectDir = dirname(__DIR__, 2);
$vendor = "{$projectDir}/vendor/";
if (!file_exists($vendor . 'autoload.php')) {
    die('Please install via Composer before running tests.');
}

require_once $vendor . 'brain/monkey/inc/patchwork-loader.php';
require_once $vendor . 'autoload.php';
unset($vendor);

putenv('PROJECT_DIR=' . $projectDir);
putenv('TESTS_PATH=' . __DIR__);

require_once __DIR__ . '/stubs/woocommerce.php';

unset($projectDir);
