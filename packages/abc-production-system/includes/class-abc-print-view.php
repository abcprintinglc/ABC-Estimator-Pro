<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('ABC_Print_View')) {
class ABC_Print_View {

    public function __construct() {
        add_action('template_redirect', [$this, 'render_print']);
    }

    /**
     * Access via: /?abc_action=print_estimate&id=123
     */
    public function render_print() {
        if (!isset($_GET['abc_action']) || $_GET['abc_action'] !== 'print_estimate') {
            return;
        }
        $post_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$post_id) {
            return;
        }

        if (!current_user_can('read_post', $post_id)) {
            wp_die('Unauthorized');
        }

        include ABCPS_PATH . 'templates/print-view.php';
        exit;
    }
}}
