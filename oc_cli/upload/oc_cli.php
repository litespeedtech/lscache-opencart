<?php

// Function definitions
if (!function_exists('oc_cli_output')) {
    function oc_cli_output($message, $exit_status = NULL) {
        echo $message . PHP_EOL;

        if ($exit_status !== NULL) {
            exit($exit_status);
        }
    }
}

if (!function_exists('oc_cli_find_version')) {
    function oc_cli_find_version() {
        $current_dir = dirname(__FILE__);

        $index_contents = file_get_contents($current_dir . '/index.php');

        $matches = array();

        preg_match('~define\s*\(\s*\'VERSION\'\s*,\s*\'(.*?)\'\s*\)\s*;~', $index_contents, $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        } else {
            oc_cli_output("Cannot find OpenCart version.", 1);
        }
    }
}

// The action starts... Let's check for the CLI mode
if (php_sapi_name() != 'cli') {
    header("Location:/");
    oc_cli_output("Get out.", 1);
}

// Version
if (!defined('VERSION')) define('VERSION', oc_cli_find_version()); // Version according to OC version.

// Status constant. Should be set to TRUE.
if (!defined('OPENCART_CLI_MODE')) define('OPENCART_CLI_MODE', TRUE);

// Change directory to allow the script to be called from anywhere.
chdir(dirname(__FILE__));

// Determine the type of app, so we can use the admin folder later
$opencart_app = 'catalog';
if (empty($argv[1]) || !is_dir($argv[1])) {
    oc_cli_output("Invalid request. Expecting: catalog, <admin-folder-name>", 1);
} else {
    $opencart_app = $argv[1];
}

// If app is "catalog", use the config in the root dir. Otherwise, regard $opencart_app as the OpenCart admin folder.
$config_root = $opencart_app == 'catalog' ? './' : './' . $opencart_app . '/';

if (!isset($_SERVER['SERVER_PORT'])) {
    $_SERVER['SERVER_PORT'] = 80;
}

// Configuration
if (is_file($config_root . 'config.php')) {
    require_once($config_root . 'config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
    oc_cli_output("OpenCart not installed.", 1);
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

$application_config = 'oc_cli';

// Application
require_once(DIR_SYSTEM . 'framework.php');