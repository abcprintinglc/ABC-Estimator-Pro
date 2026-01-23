<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Core CPT + Job Jacket meta + history log + print view.
 */
class ABC_Estimator_Core {

    /**
     * Meta keys managed by the Job Jacket.
     * @var string[]
     */
    private $meta_keys = [
        'abc_invoice_number',
        'abc_order_date',
        'abc_due_date',
        'abc_approval_date',
        'abc_is_rush',
        'abc_status',
        'abc_estimate_data',
        'abc_history_log',
        'abc_is_imported',
    ];

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_abc_estimate', [$this, 'save_job_jacket_data'], 10, 2);
        add_action('template_redirect', [$this, 'handle_print_view']);

        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('init', [$this, 'register_meta']);
    }

    /**
     * Register 'Estimate / Job' post type
     */
    public function register_cpt() {
        $labels = [
            'name'          => 'Estimates & Jobs',
            'singular_name' => 'Job',
            'menu_name'     => 'Estimator / Log',
            'add_new_item'  => 'New Estimate',
            'edit_item'     => 'Edit Job Jacket',
        ];

        register_post_type('abc_estimate', [
            'labels'            => $labels,
            'public'            => false, // internal
            'show_ui'           => true,
            'show_in_menu'      => true,
            'supports'          => ['title', 'revisions', 'author'],
            'menu_icon'         => 'dashicons-calculator',
            'map_meta_cap'      => true,
            'capability_type'   => 'post',
            'exclude_from_search' => true,
            'show_in_rest'      => false,
        ]);
    }

    /**
     * Register meta keys so they're visible to WP and can be protected/sanitized.
     */
    public function register_meta() {
        // Dates and invoice stored as strings.
        register_post_meta('abc_estimate', 'abc_invoice_number', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => [$this, 'sanitize_invoice'],
            'auth_callback'     => function () { return current_user_can('edit_posts'); },
        ]);

        foreach (['abc_order_date','abc_due_date','abc_approval_date'] as $k) {
            register_post_meta('abc_estimate', $k, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => [$this, 'sanitize_date'],
                'auth_callback'     => function () { return current_user_can('edit_posts'); },
            ]);
        }

        register_post_meta('abc_estimate', 'abc_is_rush', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => function ($v) { return $v === '1' ? '1' : '0'; },
            'auth_callback'     => function () { return current_user_can('edit_posts'); },
        ]);


        // Workflow status / stage.
        register_post_meta('abc_estimate', 'abc_status', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => [$this, 'sanitize_status'],
            'auth_callback'     => function () { return current_user_can('edit_posts'); },
        ]);

        // JSON string for line items grid.
        register_post_meta('abc_estimate', 'abc_estimate_data', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => [$this, 'sanitize_json'],
            'auth_callback'     => function () { return current_user_can('edit_posts'); },
        ]);

        // History log stored as array (serialized).
        register_post_meta('abc_estimate', 'abc_history_log', [
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => [$this, 'sanitize_history'],
            'auth_callback'     => function () { return current_user_can('edit_posts'); },
        ]);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'abc_job_jacket_meta',
            'Job Jacket Details',
            [$this, 'render_job_jacket_box'],
            'abc_estimate',
            'normal',
            'high'
        );

        add_meta_box(
            'abc_history_log',
            'History / Change Log',
            [$this, 'render_history_box'],
            'abc_estimate',
            'side',
            'default'
        );
    }

    /**
     * Render Job Jacket meta box.
     */
    public function render_job_jacket_box($post) {
        $invoice_num   = (string) get_post_meta($post->ID, 'abc_invoice_number', true);
        $order_date    = (string) get_post_meta($post->ID, 'abc_order_date', true);
        $due_date      = (string) get_post_meta($post->ID, 'abc_due_date', true);
        $approval_date = (string) get_post_meta($post->ID, 'abc_approval_date', true);
        $is_rush       = (string) get_post_meta($post->ID, 'abc_is_rush', true);
        $status        = (string) get_post_meta($post->ID, 'abc_status', true);
        if ($status === '') { $status = 'estimate'; }
        $estimate_json = (string) get_post_meta($post->ID, 'abc_estimate_data', true);

        if ($estimate_json === '') {
            $estimate_json = '[]';
        }

        wp_nonce_field('abc_save_estimate', 'abc_estimate_nonce');
        ?>
        <div class="abc-jacket-grid">
            <p>
                <label><strong>Invoice # (tttt-yy):</strong></label><br>
                <input type="text" name="abc_invoice_number" value="<?php echo esc_attr($invoice_num); ?>" placeholder="1234-24" style="width: 220px;">
            </p>
            <p>
                <label>Order Date:</label><br>
                <input type="date" name="abc_order_date" value="<?php echo esc_attr($order_date); ?>">
            </p>
            <p>
                <label>Approval Date:</label><br>
                <input type="date" name="abc_approval_date" value="<?php echo esc_attr($approval_date); ?>">
            </p>
            <p>
                <label><strong>Due Date:</strong></label><br>
                <input type="date" name="abc_due_date" value="<?php echo esc_attr($due_date); ?>">
            </p>
            <p>
                <label style="color:#b32d2e; font-weight:bold;">Rush Job?</label><br>
                <label>
                    <input type="checkbox" name="abc_is_rush" value="1" <?php checked($is_rush, '1'); ?>> Yes, Rush!
                </label>
            </p>

            <p>
                <label><strong>Current Stage:</strong></label><br>
                <select name="abc_status" style="width: 260px;">
                    <option value="estimate" <?php selected($status, 'estimate'); ?>>Draft / Estimate</option>
                    <option value="pending" <?php selected($status, 'pending'); ?>>Pending Approval</option>
                    <option value="production" <?php selected($status, 'production'); ?>>In Production (Live Job)</option>
                    <option value="completed" <?php selected($status, 'completed'); ?>>Completed / Shipped</option>
                </select>
            </p>

            <input type="hidden" name="abc_estimate_data" id="abc_estimate_data" value="<?php echo esc_attr($estimate_json); ?>">

            <hr>
            <div id="abc-react-estimate-builder-mount">
                <p><em>(Line item grid renders here)</em></p>
            </div>

            <?php
            $print_url = esc_url(home_url('/?abc_action=print_estimate&id=' . $post->ID));
            ?>
            <p>
                <a class="button" href="<?php echo $print_url; ?>" target="_blank" rel="noopener">Print View</a>
            </p>
        </div>
        <?php
    }

    /**
     * Render History meta box (read-only list + manual note append).
     */
    public function render_history_box($post) {
        $history = get_post_meta($post->ID, 'abc_history_log', true);

        if (!empty($history) && is_array($history)) {
            echo '<ul style="max-height:200px; overflow-y:auto; padding-left:15px; margin-top:0;">';
            foreach (array_reverse($history) as $entry) {
                $date = isset($entry['date']) ? $entry['date'] : '';
                $user = isset($entry['user']) ? $entry['user'] : '';
                $note = isset($entry['note']) ? $entry['note'] : '';
                echo '<li><small>' . esc_html($date) . ' by ' . esc_html($user) . ':<br>' . esc_html($note) . '</small></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No history yet.</p>';
        }

        ?>
        <textarea name="abc_manual_note" placeholder="Add a note to the log..." rows="3" style="width:100%; margin-top:10px;"></textarea>
        <p class="description" style="margin-top:6px;">Tip: Use this for call notes, material changes, approvals, etc.</p>
        <?php
    }

    /**
     * Save Job Jacket meta + history log.
     */
    public function save_job_jacket_data($post_id, $post) {
        if (!isset($_POST['abc_estimate_nonce']) || !wp_verify_nonce($_POST['abc_estimate_nonce'], 'abc_save_estimate')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ($post instanceof WP_Post && $post->post_type !== 'abc_estimate') {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $changes = [];

        // Rush checkbox.
        $old_rush = (string) get_post_meta($post_id, 'abc_is_rush', true);
        $new_rush = isset($_POST['abc_is_rush']) ? '1' : '0';
        if ($new_rush !== $old_rush) {
            update_post_meta($post_id, 'abc_is_rush', $new_rush);
            $changes[] = 'Rush status changed to ' . ($new_rush === '1' ? 'YES' : 'NO');
        }

        // Standard fields.
        $fields = [
            'abc_invoice_number',
            'abc_order_date',
            'abc_due_date',
            'abc_approval_date',
	        'abc_status',
            'abc_estimate_data',
        ];

        foreach ($fields as $field) {
            if (!isset($_POST[$field])) {
                continue;
            }

            $old = get_post_meta($post_id, $field, true);
            $new = $_POST[$field];

            if ($field === 'abc_estimate_data') {
                $new = $this->sanitize_json($new);
            } elseif ($field === 'abc_status') {
                $new = $this->sanitize_status($new);
            } elseif ($field === 'abc_invoice_number') {
                $new = $this->sanitize_invoice($new);
            } elseif (in_array($field, ['abc_order_date','abc_due_date','abc_approval_date'], true)) {
                $new = $this->sanitize_date($new);
            } else {
                $new = sanitize_text_field($new);
            }

            if ((string)$new !== (string)$old) {
                update_post_meta($post_id, $field, $new);
                if ($field === 'abc_estimate_data') {
                    $changes[] = 'Line items updated.';
                } else {
                    $changes[] = str_replace('abc_', '', $field) . ' updated.';
                }
            }
        }


        // Manual history note (optional).
        $manual_note = '';
        if (isset($_POST['abc_manual_note'])) {
            $manual_note = sanitize_textarea_field((string) $_POST['abc_manual_note']);
            $manual_note = trim($manual_note);
        }

        // Append to History Log (auto changes + manual note).
        $entries_to_add = [];

        if (!empty($changes)) {
            $user = wp_get_current_user();
            $entries_to_add[] = [
                'date' => current_time('mysql'),
                'user' => $user && isset($user->display_name) ? $user->display_name : 'Unknown',
                'note' => implode(', ', $changes),
            ];
        }

        if ($manual_note !== '') {
            $user = wp_get_current_user();
            $entries_to_add[] = [
                'date' => current_time('mysql'),
                'user' => $user && isset($user->display_name) ? $user->display_name : 'Unknown',
                'note' => 'Manual Note: ' . $manual_note,
            ];
        }

        if (!empty($entries_to_add)) {
            $current_log = get_post_meta($post_id, 'abc_history_log', true);
            if (!is_array($current_log)) {
                $current_log = [];
            }

            foreach ($entries_to_add as $e) {
                $current_log[] = $e;
            }

            // Keep history from growing forever.
            if (count($current_log) > 300) {
                $current_log = array_slice($current_log, -300);
            }

            update_post_meta($post_id, 'abc_history_log', $current_log);
        }

        // Update the search index stored in post_excerpt (fast WP search).
        $this->update_search_index($post_id);
    }

    /**
     * Clean print view: /?abc_action=print_estimate&id=123
     */
    public function handle_print_view() {
        if (!isset($_GET['abc_action'], $_GET['id'])) {
            return;
        }
        if ((string) $_GET['abc_action'] !== 'print_estimate') {
            return;
        }

        $post_id = absint($_GET['id']);
        if (!$post_id) {
            wp_die('Invalid request.');
        }

        // Ensure this is the right post type.
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'abc_estimate') {
            wp_die('Not found.');
        }

        if (!current_user_can('read_post', $post_id)) {
            wp_die('Unauthorized');
        }

        // Avoid theme output.
        status_header(200);
        nocache_headers();

        $template = ABC_ESTIMATOR_PRO_DIR . 'templates/print-view.php';
        if (!file_exists($template)) {
            wp_die('Print template missing.');
        }

        include $template;
        exit;
    }

    /**
     * Admin assets.
     */
    public function admin_assets($hook) {
        // Load on abc_estimate screens + our admin pages.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_estimate_screen = ($screen && $screen->post_type === 'abc_estimate');
        $is_tools_screen = ($screen && in_array($screen->id, [
            'abc_estimate_page_abc-import-settings',
            'abc_estimate_page_abc-log-book',
        ], true));

        if (!$is_estimate_screen && !$is_tools_screen) {
            return;
        }

        wp_enqueue_style(
            'abc-estimator-pro-admin',
            ABC_ESTIMATOR_PRO_URL . 'assets/style.css',
            [],
            ABC_ESTIMATOR_PRO_VERSION
        );

        wp_enqueue_script(
            'abc-estimator-pro-admin',
            ABC_ESTIMATOR_PRO_URL . 'assets/app.js',
            ['jquery'],
            ABC_ESTIMATOR_PRO_VERSION,
            true
        );

        wp_localize_script('abc-estimator-pro-admin', 'ABC_ESTIMATOR_PRO', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('abc_log_book_nonce'),
            'post_id'  => $is_estimate_screen && isset($screen->base) && $screen->base === 'post' ? get_the_ID() : 0,
        ]);
    }

    /**
     * Sanitizers
     */
    public function sanitize_invoice($value) {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '';
        }
        // Expect: 4 digits-2 digits.
        if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
            // Keep the raw value to avoid data loss, but strip tags.
            return sanitize_text_field($value);
        }
        return $value;
    }

    public function sanitize_date($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        // Basic YYYY-MM-DD.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return sanitize_text_field($value);
        }
        return $value;
    }


    public function sanitize_status($value) {
        $value = strtolower(trim((string) $value));
        $allowed = ['estimate','pending','production','completed'];
        if (!in_array($value, $allowed, true)) {
            return 'estimate';
        }
        return $value;
    }

    /**
     * Build and persist a compact search index into post_excerpt so WP can search quickly.
     * Includes invoice #, status, due date, and a short summary of line items.
     */
    private function update_search_index($post_id) {
        static $in_update = false;
        if ($in_update) {
            return;
        }
        $in_update = true;

        $invoice = (string) get_post_meta($post_id, 'abc_invoice_number', true);
        $status  = (string) get_post_meta($post_id, 'abc_status', true);
        $due     = (string) get_post_meta($post_id, 'abc_due_date', true);
        $json    = (string) get_post_meta($post_id, 'abc_estimate_data', true);

        $status = $this->sanitize_status($status);

        $summary = '';
        $decoded = json_decode(wp_unslash($json), true);
        if (is_array($decoded)) {
            $parts = [];
            $walker = function ($node) use (&$walker, &$parts) {
                if (is_array($node)) {
                    foreach ($node as $k => $v) {
                        if (is_array($v)) {
                            $walker($v);
                            continue;
                        }
                        if (is_string($v) || is_numeric($v)) {
                            $key = is_string($k) ? strtolower($k) : '';
                            if (in_array($key, ['description','desc','name','item','product','service','sku','notes','note'], true)) {
                                $parts[] = (string) $v;
                            }
                        }
                    }
                }
            };
            $walker($decoded);

            if (!empty($parts)) {
                $summary = implode(' ', $parts);
            }
        }

        $summary = preg_replace('/\s+/', ' ', trim((string) $summary));
        if (strlen($summary) > 3500) {
            $summary = substr($summary, 0, 3500);
        }

        $index = trim(sprintf('Invoice: %s | Status: %s | Due: %s | %s', $invoice, $status, $due, $summary));

        // Compare to current excerpt and update only if changed.
        $post = get_post($post_id);
        $current = $post ? (string) $post->post_excerpt : '';
        if ($index !== $current) {
            // Prevent recursion by temporarily removing our save handler.
            remove_action('save_post_abc_estimate', [$this, 'save_job_jacket_data'], 10);
            wp_update_post([
                'ID' => (int) $post_id,
                'post_excerpt' => $index,
            ]);
            add_action('save_post_abc_estimate', [$this, 'save_job_jacket_data'], 10, 2);
        }

        $in_update = false;
    }

    public function sanitize_json($value) {
        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value);
        }
        $value = (string) $value;
        $value = wp_unslash($value);
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Preserve as plain string (sanitized) rather than wipe.
            return sanitize_textarea_field($value);
        }
        // Re-encode to normalize.
        return wp_json_encode($decoded);
    }

    public function sanitize_history($value) {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) { continue; }
            $out[] = [
                'date' => isset($entry['date']) ? sanitize_text_field((string)$entry['date']) : '',
                'user' => isset($entry['user']) ? sanitize_text_field((string)$entry['user']) : '',
                'note' => isset($entry['note']) ? sanitize_text_field((string)$entry['note']) : '',
            ];
        }
        return $out;
    }
}
