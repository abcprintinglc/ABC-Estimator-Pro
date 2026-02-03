<?php
/**
 * Plugin Name: ABC Suite (Estimator, Production, Designer)
 * Description: ABC Estimator Pro plus Production System and B2B Designer components.
 * Version: 1.8.0
 * Author: ABC Printing Co. LLC
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) { exit; }

define('ABC_ESTIMATOR_PRO_VERSION', '1.8.0');
define('ABC_ESTIMATOR_PRO_DIR', plugin_dir_path(__FILE__));
define('ABC_ESTIMATOR_PRO_URL', plugin_dir_url(__FILE__));

/**
 * Check whether a separate plugin (by plugin basename) is active.
 */
function abc_estimator_pro_is_plugin_active($plugin_basename) {
    $active_plugins = (array) get_option('active_plugins', []);
    if (in_array($plugin_basename, $active_plugins, true)) {
        return true;
    }

    if (is_multisite()) {
        $network_active = (array) get_site_option('active_sitewide_plugins', []);
        if (isset($network_active[$plugin_basename])) {
            return true;
        }
    }

    return false;
}

require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-estimator-core.php';
require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-log-book-logic.php';
require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-csv-manager.php';
require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-frontend.php';

if (
    !defined('ABCPS_VERSION')
    && !class_exists('ABC_CPT')
    && !abc_estimator_pro_is_plugin_active('abc-production-system/abc-production-system.php')
) {
    require_once ABC_ESTIMATOR_PRO_DIR . 'packages/abc-production-system/abc-production-system.php';
}

if (
    !defined('ABC_B2B_DESIGNER_VERSION')
    && !class_exists('ABC_B2B_Designer_Plugin')
    && !abc_estimator_pro_is_plugin_active('abc-b2b-designer/abc-b2b-designer.php')
) {
    require_once ABC_ESTIMATOR_PRO_DIR . 'packages/abc-b2b-designer/abc-b2b-designer.php';
}

/**
 * Bootstrap
 */
add_action('plugins_loaded', function () {
    // Instantiate core components.
    if (class_exists('ABC_Estimator_Core')) {
        new ABC_Estimator_Core();
    }
    if (class_exists('ABC_Log_Book_Logic')) {
        new ABC_Log_Book_Logic();
    }
    if (class_exists('ABC_CSV_Manager')) {
        new ABC_CSV_Manager();
    }
    if (class_exists('ABC_Frontend_Display')) {
        new ABC_Frontend_Display();
    }
});

/**
 * Activation: register CPT then flush rewrite rules (safe even if CPT is non-public).
 */
register_activation_hook(__FILE__, function () {
    if (class_exists('ABC_Estimator_Core')) {
        $core = new ABC_Estimator_Core();
        $core->register_cpt();
    }
    if (function_exists('abc_production_system_activate')) {
        abc_production_system_activate();
    }
    if (function_exists('abc_b2b_designer_activate')) {
        abc_b2b_designer_activate();
    }
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
