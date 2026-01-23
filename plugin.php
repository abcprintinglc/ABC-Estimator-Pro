<?php
/**
 * Plugin Name: ABC Estimator Pro
 * Description: Internal estimator + job jacket tooling for ABC Printing (CPT, Job Jacket meta, history log, CSV import tools, urgency logic, print view).
 * Version: 1.7.3
 * Author: ABC Printing Co. LLC
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) { exit; }

define('ABC_ESTIMATOR_PRO_VERSION', '1.7.3');
define('ABC_ESTIMATOR_PRO_DIR', plugin_dir_path(__FILE__));
define('ABC_ESTIMATOR_PRO_URL', plugin_dir_url(__FILE__));

require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-estimator-core.php';
require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-log-book-logic.php';
require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-csv-manager.php';
require_once ABC_ESTIMATOR_PRO_DIR . 'includes/class-abc-frontend.php';

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
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
