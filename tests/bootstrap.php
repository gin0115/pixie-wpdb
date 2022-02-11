<?php

/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once getenv('WP_PHPUNIT__DIR') . '/includes/functions.php';

// Base path of the src directory.

define('SRC_PATH', dirname(__DIR__, 1) . '/src');

tests_add_filter(
    'muplugins_loaded',
    function () {
        global $wp_version;
        echo 'WordPress Version' . $wp_version . PHP_EOL;
        echo 'WordPress Version' . $wp_version . PHP_EOL;
        echo 'WordPress Version' . $wp_version . PHP_EOL;
        echo 'WordPress Version' . $wp_version . PHP_EOL;
        echo 'WordPress Version' . $wp_version . PHP_EOL;
    }
);

// Start up the WP testing environment.
require getenv('WP_PHPUNIT__DIR') . '/includes/bootstrap.php';
