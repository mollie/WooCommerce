<?php # -*- coding: utf-8 -*-

$projectDir = dirname(dirname(__DIR__));
$vendor = "{$projectDir}/vendor/";

if (!file_exists($vendor . 'autoload.php')) {
    die('Please install via Composer before running tests.');
}

require_once __DIR__ . '/Stubs/stubs.php';
require_once $vendor . 'brain/monkey/inc/patchwork-loader.php';
require_once $vendor . 'autoload.php';

putenv('PROJECT_DIR=' . $projectDir);
putenv('TESTS_PATH=' . __DIR__);

unset($vendor, $projectDir);
