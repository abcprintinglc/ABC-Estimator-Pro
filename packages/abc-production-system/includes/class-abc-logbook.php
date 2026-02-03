<?php
if (!defined('ABSPATH')) { exit; }

class ABC_Logbook {

    public function __construct() {
        add_action('wp_ajax_abc_search_estimates', [$this, 'ajax_search_estimates']);

        // Admin row action: Duplicate (Save as New)
        add_filter('post_row_actions', [$this, 'add_duplicate_row_action'], 10, 2);
        add_action('admin_post_abc_duplicate_estimate', [$this, 'handle_duplicate_estimate']);
    }

    /**
     * Urgency engine.
     */
    public static function get_urgency_status($due_date_str) {
        if (empty($due_date_str)) return 'normal';
        $diff = (strtotime($due_date_str . ' 23:59:59') - current_time('timestamp')) / DAY_IN_SECONDS;
        if ($diff < 0) return 'urgent';
        if ($diff <= 1) return 'warning';
        return 'normal';
    }

    private static function due_date_for_display($due_date_str) {
        if (empty($due_date_str)) return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $due_date_str, $m)) {
            $year = intval($m[1]);
            if ($year < 2026) return '';
        }
        return $due_date_str;
    }

    /**
     * AJAX: Search endpoint used by both Admin Dashboard + Frontend Shortcode.
     */
    public function ajax_search_estimates() {
        check_ajax_referer('abc_log_book_nonce', 'nonce');

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';

        $args = [
            'post_type'      => 'abc_estimate',
            'posts_per_page' => 50,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($term !== '') {
            // Title/excerpt search PLUS meta search for invoice/client/phone.
            $args['s'] = $term;
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'abc_invoice_number',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'abc_client_name',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'abc_client_phone',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'abc_job_description',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new WP_Query($args);
        $results = [];

        foreach ($query->posts as $post) {
            $due_raw  = get_post_meta($post->ID, 'abc_due_date', true);
            $due      = self::due_date_for_display($due_raw);
            $rush     = get_post_meta($post->ID, 'abc_is_rush', true);
            $status   = get_post_meta($post->ID, 'abc_status', true) ?: 'estimate';
            $client   = get_post_meta($post->ID, 'abc_client_name', true);

            $urgency = self::get_urgency_status($due);
            if ($rush === '1') $urgency = 'urgent';

            $results[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'client'        => $client,
                'invoice'        => get_post_meta($post->ID, 'abc_invoice_number', true),
                'due_date'      => $due,
                'stage'         => $status,
                'is_rush'       => ($rush === '1'),
                'urgency'       => $urgency,
                'urgency_class' => 'urgency-' . $urgency,
                'edit_url'      => get_edit_post_link($post->ID, 'raw'),
                'print_url'     => home_url('/?abc_action=print_estimate&id=' . $post->ID),
            ];
        }

        wp_send_json_success($results);
    }

    /**
     * Add "Duplicate" action link on the WP list table.
     */
    public function add_duplicate_row_action($actions, $post) {
        if ($post->post_type !== 'abc_estimate') return $actions;

        $url = wp_nonce_url(admin_url('admin-post.php?action=abc_duplicate_estimate&id=' . $post->ID), 'abc_dup_nonce');
        $actions['abc_duplicate'] = '<a href="' . esc_url($url) . '">Duplicate</a>';
        return $actions;
    }

    /**
     * Handle Duplicate Action.
     */
    public function handle_duplicate_estimate() {
        check_admin_referer('abc_dup_nonce');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;

        if (!$post || $post->post_type !== 'abc_estimate') wp_die('Error');

        $new_id = wp_insert_post([
            'post_type'   => 'abc_estimate',
            'post_status' => 'draft',
            'post_title'  => $post->post_title . ' - COPY',
            'post_author' => get_current_user_id(),
        ]);

        // Copy Meta (Exclude invoice/history/import flags)
        $meta = get_post_meta($id);
        foreach ($meta as $k => $v) {
            if (in_array($k, ['abc_invoice_number', 'abc_history_log', 'abc_is_imported'], true)) continue;
            foreach ($v as $val) {
                add_post_meta($new_id, $k, maybe_unserialize($val));
            }
        }

        // Init History
        update_post_meta($new_id, 'abc_history_log', [[
            'date' => current_time('mysql'),
            'user' => wp_get_current_user()->display_name,
            'note' => 'Duplicated from #' . $id,
        ]]);

        wp_safe_redirect(get_edit_post_link($new_id, 'raw'));
        exit;
    }
}
