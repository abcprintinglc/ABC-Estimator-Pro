<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Log Book / Search endpoint + urgency status.
 * Includes: Admin Log Book dashboard page + Duplicate action.
 */
class ABC_Log_Book_Logic {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('wp_ajax_abc_search_estimates', [$this, 'ajax_search_estimates']);

        // Admin row action: Duplicate (Save as New)
        add_filter('post_row_actions', [$this, 'add_duplicate_row_action'], 10, 2);
        add_action('admin_post_abc_duplicate_estimate', [$this, 'handle_duplicate_estimate']);
    }

    /**
     * Register the Log Book submenu page under the CPT menu.
     */
    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=abc_estimate',
            'Log Book',
            'Log Book',
            'edit_posts',
            'abc-log-book',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Admin Log Book dashboard UI shell.
     * JS renders the fast-search table into #abc-log-results.
     */
    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Estimator Log Book</h1>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=abc_estimate')); ?>" class="page-title-action">Create New Estimate</a>

            <div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <div style="display:flex; gap:10px; margin-bottom:15px; align-items:center;">
                    <input type="text" id="abc-log-search" placeholder="Fast Search (Invoice, Client, Job Name, keywords)..." style="width:100%; max-width:520px; padding:8px; font-size:15px;">
                    <span class="spinner" id="abc-admin-spinner" style="float:none; margin:0;"></span>
                </div>

                <div id="abc-log-results">
                    <p class="description">Start typing to search estimates...</p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add "Duplicate" action link on the CPT list table.
     */
    public function add_duplicate_row_action($actions, $post) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'abc_estimate') {
            return $actions;
        }
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=abc_duplicate_estimate&id=' . (int) $post->ID),
            'abc_dup_nonce'
        );
        $actions['abc_duplicate'] = '<a href="' . esc_url($url) . '">Duplicate</a>';

        return $actions;
    }

    /**
     * Duplicate an estimate/job (clones meta + line items).
     * NOTE: Invoice number is cleared to avoid duplicate invoice collisions.
     */
    public function handle_duplicate_estimate() {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('abc_dup_nonce');

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id) {
            wp_die('Invalid request.');
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'abc_estimate') {
            wp_die('Not found.');
        }

        // Create the new post.
        $new_id = wp_insert_post([
            'post_type'   => 'abc_estimate',
            'post_status' => 'draft',
            'post_title'  => $post->post_title . ' - COPY',
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($new_id)) {
            wp_die('Could not duplicate: ' . esc_html($new_id->get_error_message()));
        }

        // Copy meta (skip system keys and history, invoice, import flag).
        $all_meta = get_post_meta($id);
        foreach ($all_meta as $key => $values) {
            if (in_array($key, ['_edit_lock','_edit_last'], true)) {
                continue;
            }
            if (in_array($key, ['abc_history_log','abc_invoice_number','abc_is_imported'], true)) {
                continue;
            }
            foreach ($values as $v) {
                add_post_meta($new_id, $key, maybe_unserialize($v));
            }
        }

        // Clear invoice number and mark not imported.
        update_post_meta($new_id, 'abc_invoice_number', '');
        update_post_meta($new_id, 'abc_is_imported', '0');

        // Start a fresh history log.
        $user = wp_get_current_user();
        update_post_meta($new_id, 'abc_history_log', [[
            'date' => current_time('mysql'),
            'user' => $user && isset($user->display_name) ? $user->display_name : 'Unknown',
            'note' => 'Duplicated from #' . (int) $id,
        ]]);

        wp_safe_redirect(get_edit_post_link($new_id, 'raw'));
        exit;
    }

    /**
     * Calculate urgency.
     * Returns: 'normal', 'warning' (<=1 day), 'urgent' (overdue/today)
     */
    public static function get_urgency_status($due_date_str) {
        $due_date_str = trim((string) $due_date_str);
        if ($due_date_str === '') {
            return 'normal';
        }

        // Interpret as local end-of-day.
        $due_ts = strtotime($due_date_str . ' 23:59:59');
        if (!$due_ts) {
            return 'normal';
        }

        $today_ts = current_time('timestamp');
        $diff_seconds = $due_ts - $today_ts;
        $diff_days = $diff_seconds / DAY_IN_SECONDS;

        if ($diff_days < 0) {
            return 'urgent';
        }
        if ($diff_days <= 1) {
            return 'warning';
        }
        return 'normal';
    }

    /**
     * AJAX: Fast search for estimates.
     * If term is empty, returns the 50 most recent.
     */
    public function ajax_search_estimates() {
        check_ajax_referer('abc_log_book_nonce', 'nonce');

        $search_term = isset($_POST['term']) ? sanitize_text_field((string) $_POST['term']) : '';

        $args = [
            'post_type'      => 'abc_estimate',
            'posts_per_page' => 50,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($search_term !== '') {
            $args['s'] = $search_term;
        }

        $query = new WP_Query($args);
        $results = [];

        foreach ($query->posts as $post) {
            $due_date = (string) get_post_meta($post->ID, 'abc_due_date', true);
            $is_rush  = (string) get_post_meta($post->ID, 'abc_is_rush', true);
            $stage    = (string) get_post_meta($post->ID, 'abc_status', true);
            if ($stage === '') {
                $stage = 'estimate';
            }

            $urgency = self::get_urgency_status($due_date);
            if ($is_rush === '1') {
                $urgency = 'urgent';
            }

            $results[] = [
                'id'            => (int) $post->ID,
                'title'         => (string) $post->post_title,
                'invoice'       => (string) get_post_meta($post->ID, 'abc_invoice_number', true),
                'due_date'      => $due_date,
                'status'        => (string) $post->post_status,
                'stage'         => $stage,
                'is_rush'       => $is_rush === '1',
                'urgency'       => $urgency,
                'urgency_class' => 'urgency-' . $urgency,
                'edit_url'      => get_edit_post_link($post->ID, 'raw'),
                'print_url'     => home_url('/?abc_action=print_estimate&id=' . $post->ID),
            ];
        }

        wp_send_json_success($results);
    }
}
