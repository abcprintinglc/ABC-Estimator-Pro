<?php
/**
 * Plugin Name: ABC Production System
 * Description: Full production OS. Modular architecture for Job Jackets, Log Book, CSV Import, and Print Views.
 * Version: 2.0.3
 * Author: ABC Printing Co.
 */

if (!defined('ABSPATH')) { exit; }

// Constants
define('ABCPS_VERSION', '2.0.3');
define('ABCPS_PATH', plugin_dir_path(__FILE__));
define('ABCPS_URL', plugin_dir_url(__FILE__));

// Core includes (modular)
require_once ABCPS_PATH . 'includes/class-abc-cpt.php';
require_once ABCPS_PATH . 'includes/class-abc-job-jacket.php';
require_once ABCPS_PATH . 'includes/class-abc-logbook.php';
require_once ABCPS_PATH . 'includes/class-abc-csv.php';
require_once ABCPS_PATH . 'includes/class-abc-print-view.php';
require_once ABCPS_PATH . 'includes/class-abc-dashboard.php';
require_once ABCPS_PATH . 'includes/class-abc-frontend.php';

/**
 * Bootstrap
 */
add_action('plugins_loaded', function () {
    if (class_exists('ABC_CPT')) new ABC_CPT();
    if (class_exists('ABC_Job_Jacket')) new ABC_Job_Jacket();
    if (class_exists('ABC_Logbook')) new ABC_Logbook();
    if (class_exists('ABC_Production_CSV_Manager')) new ABC_Production_CSV_Manager();
    if (class_exists('ABC_Print_View')) new ABC_Print_View();
    if (class_exists('ABC_Dashboard')) new ABC_Dashboard();
    if (class_exists('ABC_Frontend')) new ABC_Frontend();
});

/**
 * Activation: Register CPT immediately so rewrite rules exist.
 */
if (!function_exists('abc_production_system_activate')) {
    function abc_production_system_activate() {
        if (!class_exists('ABC_CPT')) {
            require_once ABCPS_PATH . 'includes/class-abc-cpt.php';
        }
        if (class_exists('ABC_CPT')) {
            $cpt = new ABC_CPT();
            $cpt->register();
        }
        flush_rewrite_rules();
    }
}

register_activation_hook(__FILE__, 'abc_production_system_activate');

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
