<?php
/*
 * Plugin Name:       EasyApp
 * Plugin URI:        https://easy-ship.in
 * Description:       Transform your WordPress site into a mobile app with EasyApp.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            AKASH
 * Update URI:        https://easy-ship.in
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin version and directory constants
if (!defined('EASY_APP')) {
    define('EASY_APP', '1.0.0');
}

if (!defined('EASY_APP_DIR')) {
    define('EASY_APP_DIR', plugin_dir_path(__FILE__));
}

// Include the main class file
require_once EASY_APP_DIR . 'includes/ea-setting-page.php';
require_once EASY_APP_DIR . 'includes/ea-general-function.php';
require_once EASY_APP_DIR . 'includes/ea-rest-api.php';
require_once EASY_APP_DIR . 'includes/notification.php';


// Initialize the plugin
function easy_app_main() {
    new EasyAppSetting();
    new EeasyAppRestAPI();
	new EeasyAppNotification();
}

easy_app_main();
